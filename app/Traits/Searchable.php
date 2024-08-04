<?php

namespace App\Traits;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Services\JinaRerankService;
use Illuminate\Support\Collection;

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

    public static function search(string $query, int $limit = 40, bool $rerank = true): Collection
    {
        $model = new static;
        $embedding = static::getEmbedding($query);

        $results = $model->newQuery()
            ->selectRaw('*, (embedding <=> ?) as distance', [$embedding])
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        if ($rerank) {
            return static::rerankResults($query, $results);
        }

        return $results;
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
