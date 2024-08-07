<?php

namespace App\Traits;

use App\Services\JinaSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MathPHP\Statistics\Distance;

trait Searchable
{
    public static function bootSearchable()
    {
        static::saving(function ($model) {
            $model->updateSearchVectorIfNeeded();
        });
    }

    public function updateSearchVectorIfNeeded(): void
    {
        if (
            ($this->isNewSearchableRecord() && empty($this->embedding)) ||
            $this->hasSearchableChanges()
        ) {
            $this->updateSearchVector();
        }
    }

    protected function isNewSearchableRecord(): bool
    {
        return !$this->exists || $this->wasRecentlyCreated;
    }

    protected function hasSearchableChanges(): bool
    {
        $originalModel = $this->getOriginalSearchableModel();
        $originalSearchableText = $originalModel->toSearchableText();
        $newSearchableText = $this->toSearchableText();

        return $originalSearchableText !== $newSearchableText;
    }

    protected function getOriginalSearchableModel(): mixed
    {
        $originalAttributes = $this->getOriginal();
        $tempModel = new static();
        $tempModel->setRawAttributes($originalAttributes);
        $tempModel->exists = true;

        return $tempModel;
    }

    public function updateSearchVector(): void
    {
        $text = $this->toSearchableText();
        $jinaService = app(JinaSearchService::class);
        $embeddings = $jinaService->embed([$text]);

        $this->embedding = $embeddings[0]['embedding'];
    }

    abstract public function toSearchableText(): string;

    public static function search(string $query, int $limit = 40, bool $rerank = true, ?Builder $builder = null): Collection
    {
        $jinaService = app(JinaSearchService::class);
        $embedding = $jinaService->embed([$query])[0]['embedding'];

        $builder = $builder ?? static::getDefaultSearchableQuery();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $results = static::pgVectorSearch($builder, $embedding, $limit);
        } else {
            $results = static::fallbackSearch($builder, $embedding, $limit);
        }

        if ($rerank) {
            return static::rerankResults($query, $results);
        }

        return $results;
    }

    protected static function pgVectorSearch(Builder $builder, array $embedding, int $limit): Collection
    {
        $vector = new \Pgvector\Vector($embedding);
        return $builder
            ->selectRaw('*, (embedding <=> ?) as distance', [$vector])
            ->orderBy('distance')
            ->limit($limit)
            ->get();
    }

    protected static function fallbackSearch(Builder $builder, array $embedding, int $limit): Collection
    {
        return $builder->get()
            ->map(function ($item) use ($embedding) {
                $item->distance = 1 - Distance::cosineSimilarity($item->embedding, $embedding);
                return $item;
            })
            ->sortBy('distance')
            ->take($limit);
    }

    public static function getDefaultSearchableQuery(): Builder
    {
        if (method_exists(static::class, 'defaultSearchableQuery')) {
            return static::defaultSearchableQuery();
        }

        return static::query();
    }

    public function scopeSearchSimilar($query, $embedding)
    {
        return $query->selectRaw('*, (embedding <=> ?) as distance', [$embedding])
            ->orderBy('distance');
    }

    protected static function getEmbedding(string $text): array
    {
        $jinaService = app(JinaSearchService::class);
        $embeddings = $jinaService->embed([$text]);
        return $embeddings[0]['embedding'];
    }

    protected static function rerankResults(string $query, Collection $results): Collection
    {
        $jinaService = app(JinaSearchService::class);
        $results = $results->map(function($result){
            $result->relevance_score = 0;
            return $result;
        })->values();

        $documents = $results->map(function ($result) {
            return $result->toSearchableText();
        })->toArray();

        $rerankedResults = $jinaService->rerank($query, $documents);

        if (!isset($rerankedResults['results']) || !is_array($rerankedResults['results'])) {
            return $results;
        }

        return collect($rerankedResults['results'])->map(function ($rerankedResult) use ($results) {
            if (!isset($rerankedResult['index']) || !isset($results[$rerankedResult['index']])) {
                return null;
            }
            $originalResult = $results[$rerankedResult['index']];
            $originalResult->relevance_score = $rerankedResult['relevance_score'] ?? 0;
            return $originalResult;
        })->filter()->sortByDesc('relevance_score')->values();
    }
}
