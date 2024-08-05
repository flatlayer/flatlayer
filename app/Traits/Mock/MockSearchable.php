<?php

namespace App\Traits\Mock;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

trait MockSearchable
{
    protected static $mockEmbeddings = [];
    protected static $mockSearchResults = [];

    public static function setMockEmbedding(string $text, array $embedding): void
    {
        static::$mockEmbeddings[$text] = $embedding;
    }

    public static function setMockSearchResults(string $query, array $results): void
    {
        static::$mockSearchResults[$query] = $results;
    }

    public function updateSearchVector(): void
    {
        $text = $this->toSearchableText();
        $this->embedding = static::$mockEmbeddings[$text] ?? array_fill(0, 1536, 0);
    }

    public static function search(string $query, int $limit = 40, bool $rerank = true, ?Builder $builder = null): Collection
    {
        if (isset(static::$mockSearchResults[$query])) {
            return collect(static::$mockSearchResults[$query])->take($limit);
        }

        // Fallback to a simple string matching if no mock results are set
        return static::query()
            ->where('title', 'like', "%{$query}%")
            ->orWhere('content', 'like', "%{$query}%")
            ->limit($limit)
            ->get();
    }

    protected static function getEmbedding(string $text): array
    {
        return static::$mockEmbeddings[$text] ?? array_fill(0, 1536, 0);
    }

    protected static function rerankResults(string $query, Collection $results): Collection
    {
        // In the mock version, we'll just return the results as-is
        return $results;
    }
}
