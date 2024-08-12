<?php

namespace App\Query;

use Countable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EntryQueryBuilder implements Countable
{
    /**
     * Create a new EntryQueryBuilder instance.
     *
     * @param  Builder|Collection  $results  Query builder or collection of results
     * @param  bool|null  $isSearch  Flag to set if this is a search query
     */
    public function __construct(
        protected readonly Builder|Collection $results,
        protected readonly bool $isSearch = false
    ) {}

    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return $this->isSearch
            ? $this->paginateSearchResults($perPage, $pageName, $page)
            : $this->results->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Paginate search results.
     */
    protected function paginateSearchResults(int $perPage, string $pageName, ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);
        $results = $this->results->forPage($page, $perPage);

        return new LengthAwarePaginator(
            $results,
            $this->results->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    public function get(array $columns = ['*']): Collection|EloquentCollection
    {
        return $this->isSearch ? $this->results : $this->results->get($columns);
    }

    /**
     * Get results with relevance scores.
     *
     * @return Collection Items with their relevance scores
     */
    public function getWithRelevance(): Collection
    {
        $mapFunction = $this->isSearch
            ? fn ($item) => ['item' => $item, 'relevance' => $item->relevance ?? null]
            : fn ($item) => ['item' => $item, 'relevance' => null];

        return $this->isSearch
            ? $this->results->map($mapFunction)
            : $this->results->get()->map($mapFunction);
    }

    public function count(): int
    {
        return $this->results->count();
    }

    /**
     * Get the underlying query builder or collection.
     */
    public function getQuery(): Builder|Collection
    {
        return $this->results;
    }

    /**
     * Check if this query builder is handling search results.
     */
    public function isSearch(): bool
    {
        return $this->isSearch;
    }

    /**
     * Magic method to pass calls to the underlying query builder or collection.
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->results->$method(...$parameters);

        return $result === $this->results ? $this : $result;
    }
}
