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

    public function simplePaginate(
        int $perPage = 15,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null,
        EntrySerializer $arrayConverter = null,
        array $fields = [],
        ?bool $isSearch = null
    ): SimplePaginator {
        $page = $page ?: max(1, request()->input($pageName, 1));
        $total = $this->count();

        $items = $this->forPage($page, $perPage)->get($columns);

        return new SimplePaginator(
            $items,
            $total,
            $perPage,
            $page,
            $arrayConverter,
            $fields,
            $isSearch ?? $this->isSearch
        );
    }

    /**
     * Paginate search results.
     */
    public function forPage(int $page, int $perPage): static
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->isSearch
            ? new static($this->results->slice($offset, $perPage), $this->isSearch)
            : new static($this->results->skip($offset)->take($perPage), $this->isSearch);
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
