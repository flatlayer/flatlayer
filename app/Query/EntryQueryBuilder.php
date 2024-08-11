<?php

namespace App\Query;

use Countable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EntryQueryBuilder implements Countable
{
    protected Builder|Collection $results;
    protected bool $isSearch;

    /**
     * Create a new EntryQueryBuilder instance.
     *
     * @param Builder|Collection $results Query builder or collection of results
     * @param bool|null $isSearch Flag to set if this is a search query
     */
    public function __construct(Builder|Collection $results, ?bool $isSearch = null)
    {
        $this->results = $results;
        $this->isSearch = $isSearch ?? $results instanceof Collection;
    }

    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        if ($this->isSearch) {
            return $this->paginateSearchResults($perPage, $pageName, $page);
        }

        return $this->results->paginate($perPage, $columns, $pageName, $page);
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
        if ($this->isSearch) {
            return $this->results;
        }

        return $this->results->get($columns);
    }

    /**
     * Get results with relevance scores.
     *
     * @return Collection Items with their relevance scores
     */
    public function getWithRelevance(): Collection
    {
        if (!$this->isSearch) {
            return $this->results->get()->map(fn ($item) => ['item' => $item, 'relevance' => null]);
        }

        return $this->results->map(fn ($item) => ['item' => $item, 'relevance' => $item->relevance ?? null]);
    }

    public function count(): int
    {
        return $this->results instanceof Collection ? $this->results->count() : $this->results->count();
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
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = $this->results->$method(...$parameters);

        // If the method call returns the original object, return $this instead
        if ($result === $this->results) {
            return $this;
        }

        return $result;
    }
}
