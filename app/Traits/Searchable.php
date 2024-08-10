<?php

namespace App\Traits;

use App\Services\SearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Trait Searchable
 *
 * This trait provides search functionality to models.
 * It manages the updating of search vectors and provides methods for searching.
 */
trait Searchable
{
    /**
     * Boot the searchable trait.
     */
    public static function bootSearchable(): void
    {
        static::saving(function ($model) {
            $model->updateSearchVectorIfNeeded();
        });
    }

    /**
     * Update the search vector if needed.
     */
    public function updateSearchVectorIfNeeded(): void
    {
        if (
            ($this->isNewSearchableRecord() && empty($this->embedding)) ||
            $this->hasSearchableChanges()
        ) {
            $this->updateSearchVector();
        }
    }

    /**
     * Check if this is a new searchable record.
     */
    protected function isNewSearchableRecord(): bool
    {
        return ! $this->exists || $this->wasRecentlyCreated;
    }

    /**
     * Check if there are searchable changes.
     */
    protected function hasSearchableChanges(): bool
    {
        $originalModel = $this->getOriginalSearchableModel();
        $originalSearchableText = $originalModel->toSearchableText();
        $newSearchableText = $this->toSearchableText();

        return $originalSearchableText !== $newSearchableText;
    }

    /**
     * Creates a new model instance with original attributes for search-related change detection.
     */
    protected function getOriginalSearchableModel(): static
    {
        $originalAttributes = $this->getOriginal();
        $tempModel = new static;
        $tempModel->setRawAttributes($originalAttributes);
        $tempModel->exists = true;

        return $tempModel;
    }

    /**
     * Update the search vector using the model's searchable text.
     */
    public function updateSearchVector(): void
    {
        $text = $this->toSearchableText();
        $this->embedding = app(SearchService::class)->getEmbedding($text);
    }

    /**
     * Convert the model to searchable text.
     */
    abstract public function toSearchableText(): string;

    /**
     * Perform a search query.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  The maximum number of results to return
     * @param  bool  $rerank  Whether to rerank the results
     * @param  Builder|null  $builder  An optional query builder to start with
     * @return Collection The search results
     */
    public static function search(string $query, int $limit = 40, bool $rerank = true, ?Builder $builder = null): Collection
    {
        return app(SearchService::class)->search($query, $limit, $rerank, $builder);
    }

    /**
     * Scope a query to search for similar records based on embedding.
     *
     * @param  Builder  $query
     * @param  array  $embedding
     * @return Builder
     */
    public function scopeSearchSimilar($query, $embedding)
    {
        return $query->selectRaw('*, (1 - (embedding <=> ?)) as similarity', [$embedding])
            ->orderByDesc('similarity');
    }
}
