<?php

namespace App\Traits;

use App\Services\SearchService;
use Illuminate\Database\Eloquent\Builder;
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
        $this->embedding = app(SearchService::class)->getEmbedding($text);
    }

    abstract public function toSearchableText(): string;

    public static function search(string $query, int $limit = 40, bool $rerank = true, ?Builder $builder = null): Collection
    {
        return app(SearchService::class)->search($query, $limit, $rerank, $builder);
    }

    public function scopeSearchSimilar($query, $embedding)
    {
        return $query->selectRaw('*, (1 - (embedding <=> ?)) as similarity', [$embedding])
            ->orderByDesc('similarity');
    }
}
