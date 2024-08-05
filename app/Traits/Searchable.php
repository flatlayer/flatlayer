<?php

namespace App\Traits;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Database\Eloquent\Builder;
use App\Services\JinaRerankService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        if ($this->isNewSearchableRecord() || $this->hasSearchableChanges()) {
            $this->updateSearchVector();
        }
    }

    protected function isNewSearchableRecord(): bool
    {
        return !$this->exists || $this->wasRecentlyCreated;
    }

    protected function hasSearchableChanges(): bool
    {
        if ($this->isNewSearchableRecord()) {
            return true;
        }

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
        $response = OpenAI::embeddings()->create([
            'model' => config('flatlayer.search.embedding_model'),
            'input' => $text,
        ]);

        $this->embedding = $response->embeddings[0]->embedding;
    }

    abstract public function toSearchableText(): string;

    public static function search(string $query, int $limit = 40, bool $rerank = true, ?Builder $builder = null): Collection
    {
        $embedding = static::getEmbedding($query);

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
        return $builder
            ->selectRaw('*, (embedding <=> ?) as distance', [$embedding])
            ->orderBy('distance')
            ->limit($limit)
            ->get();
    }

    protected static function fallbackSearch(Builder $builder, array $embedding, int $limit): Collection
    {
        $allResults = $builder->get();

        $scoredResults = $allResults->map(function ($item) use ($embedding) {
            $itemEmbedding = $item->embedding;
            $distance = static::cosineSimilarity($embedding, $itemEmbedding);
            $item->distance = $distance;
            return $item;
        });

        return $scoredResults->sortBy('distance')->take($limit);
    }

    protected static function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        foreach ($a as $i => $valueA) {
            $valueB = $b[$i] ?? 0;
            $dotProduct += $valueA * $valueB;
            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $valueB * $valueB;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
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
        $response = OpenAI::embeddings()->create([
            'model' => config('flatlayer.search.embedding_model'),
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    protected static function rerankResults(string $query, Collection $results): Collection
    {
        $jinaService = app(JinaRerankService::class);

        $documents = $results->map(function ($result) {
            return $result->toSearchableText();
        })->toArray();

        $rerankedResults = $jinaService->rerank($query, $documents);

        return collect($rerankedResults)->map(function ($rerankedResult) use ($results) {
            $originalResult = $results[$rerankedResult['index']];
            $originalResult->relevance_score = $rerankedResult['relevance_score'];
            return $originalResult;
        })->sortByDesc('relevance_score')->values();
    }
}
