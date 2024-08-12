<?php

namespace App\Query;

use App\Query\Exceptions\InvalidFilterException;
use App\Query\Exceptions\QueryException;
use App\Query\JsonQueryBuilders\JsonQueryBuilder;
use App\Query\JsonQueryBuilders\PostgresJsonQueryBuilder;
use App\Query\JsonQueryBuilders\SqliteJsonQueryBuilder;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntryFilter
{
    protected ?string $search;

    protected Collection|Builder $builder;

    protected array $order = [];

    protected JsonQueryBuilder $jsonQueryBuilder;

    /**
     * @throws \Exception
     */
    public function __construct(Builder $builder, protected array $filters)
    {
        $this->builder = $builder;
        $this->search = $filters['$search'] ?? null;
        $this->order = $filters['$order'] ?? [];
        unset($this->filters['$search'], $this->filters['$order']);
        $this->jsonQueryBuilder = $this->createJsonQueryBuilder();
    }

    protected function createJsonQueryBuilder(): JsonQueryBuilder
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => new SqliteJsonQueryBuilder,
            'pgsql' => new PostgresJsonQueryBuilder,
            default => throw new \Exception("Unsupported database driver: {$driver}")
        };
    }

    /**
     * Apply all filters and search to the query builder.
     *
     * @throws QueryException
     */
    public function apply(): EntryQueryBuilder
    {
        try {
            $this->applyFilters($this->filters);

            if ($this->search && $this->isSearchable()) {
                $searchResults = $this->applySearch();

                return new EntryQueryBuilder($searchResults, true);
            }

            $this->applyOrder();

            return new EntryQueryBuilder($this->builder, false);
        } catch (\Exception $e) {
            throw new QueryException('Error applying filters: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Apply filters recursively to the query builder.
     *
     * @throws InvalidFilterException
     */
    protected function applyFilters(array $filters, string $boolean = 'and', ?Builder $query = null): void
    {
        $query = $query ?? $this->builder;
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        $query->$method(function ($subQuery) use ($filters) {
            foreach ($filters as $field => $value) {
                match ($field) {
                    '$or' => $this->applyOrConditions($value),
                    '$and' => $this->applyAndConditions($value),
                    '$tags' => $this->applyTagFilters($value),
                    default => $this->applyFieldFilter($subQuery, $field, $value)
                };
            }
        });
    }

    protected function applyOrConditions(array $conditions): void
    {
        $this->builder->where(function ($query) use ($conditions) {
            foreach ($conditions as $index => $condition) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->$method(function ($subQuery) use ($condition) {
                    foreach ($condition as $field => $value) {
                        if ($field === '$and') {
                            $this->applyAndConditions($value, $subQuery);
                        } else {
                            $this->applyFieldFilter($subQuery, $field, $value);
                        }
                    }
                });
            }
        });
    }

    protected function applyAndConditions(array $conditions, ?Builder $query = null): void
    {
        $query = $query ?? $this->builder;
        $query->where(function ($subQuery) use ($conditions) {
            foreach ($conditions as $condition) {
                foreach ($condition as $field => $value) {
                    $this->applyFieldFilter($subQuery, $field, $value);
                }
            }
        });
    }

    /**
     * Apply a filter to a specific field.
     *
     * @throws InvalidFilterException
     */
    protected function applyFieldFilter(Builder $query, string $field, mixed $value): void
    {
        if ($field === '$tags') {
            $this->applyTagFilters($value, $query);
        } elseif (Str::contains($field, '.')) {
            $this->applyJsonFieldFilter($query, $field, $value);
        } elseif (is_array($value)) {
            foreach ($value as $operator => $operand) {
                $this->applyOperator($query, $field, $operator, $operand);
            }
        } else {
            $query->where($field, $value);
        }
    }

    /**
     * Apply a filter to a JSON field.
     *
     * @throws InvalidFilterException
     */
    protected function applyJsonFieldFilter(Builder $query, string $field, mixed $value): void
    {
        [$jsonField, $jsonKey] = explode('.', $field, 2);
        if (is_array($value)) {
            foreach ($value as $operator => $operand) {
                $this->jsonQueryBuilder->applyOperator($query, $jsonField, $jsonKey, $operator, $operand);
            }
        } else {
            $this->jsonQueryBuilder->applyExactMatch($query, $jsonField, $jsonKey, $value);
        }
    }

    /**
     * Apply an operator to a field.
     *
     * @throws InvalidFilterException
     */
    protected function applyOperator(Builder $query, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            '$gt' => $query->where($field, '>', $value),
            '$gte' => $query->where($field, '>=', $value),
            '$lt' => $query->where($field, '<', $value),
            '$lte' => $query->where($field, '<=', $value),
            '$ne' => $query->where($field, '!=', $value),
            '$like' => $query->where($field, 'LIKE', $value),
            '$exists' => $this->applyExistsOperator($query, $field, $value),
            '$in' => $this->applyInOperator($query, $field, $value),
            '$notIn' => $this->applyNotInOperator($query, $field, $value),
            '$between' => $this->applyBetweenOperator($query, $field, $value),
            '$notBetween' => $this->applyNotBetweenOperator($query, $field, $value),
            '$null' => $query->whereNull($field),
            '$notNull' => $query->whereNotNull($field),
            '' => $query->where($field, $value),
            default => throw new InvalidFilterException("Invalid operator: $operator")
        };
    }

    /**
     * Apply the $exists operator to the query.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $field  The field to apply the operator to
     * @param  mixed  $value  The value to check for existence
     *
     * @throws InvalidFilterException If the value is invalid
     */
    protected function applyExistsOperator(Builder $query, string $field, mixed $value): void
    {
        if ($value === true || $value === 'true' || $value === 1) {
            $query->whereNotNull($field);
        } elseif ($value === false || $value === 'false' || $value === 0) {
            $query->whereNull($field);
        } else {
            throw new InvalidFilterException("Invalid value for \$exists operator: $value");
        }
    }

    /**
     * Apply the $in operator to the query.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $field  The field to apply the operator to
     * @param  mixed  $value  The values to check for inclusion
     *
     * @throws InvalidFilterException If the value is not an array
     */
    protected function applyInOperator(Builder $query, string $field, mixed $value): void
    {
        if (! is_array($value)) {
            throw new InvalidFilterException('Value for $in operator must be an array');
        }
        $query->whereIn($field, $value);
    }

    /**
     * Apply the $notIn operator to the query.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $field  The field to apply the operator to
     * @param  mixed  $value  The values to check for exclusion
     *
     * @throws InvalidFilterException If the value is not an array
     */
    protected function applyNotInOperator(Builder $query, string $field, mixed $value): void
    {
        if (! is_array($value)) {
            throw new InvalidFilterException('Value for $notIn operator must be an array');
        }
        $query->whereNotIn($field, $value);
    }

    /**
     * Apply the $between operator to the query.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $field  The field to apply the operator to
     * @param  mixed  $value  The range values
     *
     * @throws InvalidFilterException If the value is not an array with exactly two elements
     */
    protected function applyBetweenOperator(Builder $query, string $field, mixed $value): void
    {
        if (! is_array($value) || count($value) !== 2) {
            throw new InvalidFilterException('Value for $between operator must be an array with exactly two elements');
        }
        $query->whereBetween($field, $value);
    }

    /**
     * Apply the $notBetween operator to the query.
     *
     * @param  Builder  $query  The query builder instance
     * @param  string  $field  The field to apply the operator to
     * @param  mixed  $value  The range values to exclude
     *
     * @throws InvalidFilterException If the value is not an array with exactly two elements
     */
    protected function applyNotBetweenOperator(Builder $query, string $field, mixed $value): void
    {
        if (! is_array($value) || count($value) !== 2) {
            throw new InvalidFilterException('Value for $notBetween operator must be an array with exactly two elements');
        }
        $query->whereNotBetween($field, $value);
    }

    /**
     * Apply tag filters to the query.
     *
     * @param  array  $tags  The tags to filter by.
     */
    protected function applyTagFilters(array $tags, ?Builder $query = null): void
    {
        $query = $query ?? $this->builder;
        $query->whereHas('tags', function ($subQuery) use ($tags) {
            $subQuery->whereIn('name', $tags);
        });
    }

    /**
     * Apply search to the query if the model is searchable.
     */
    protected function applySearch(): Collection
    {
        $modelClass = get_class($this->builder->getModel());

        return $modelClass::search(
            $this->search,
            rerank: true,
            builder: $this->builder
        );
    }

    /**
     * Apply ordering to the query if not in search mode.
     */
    protected function applyOrder(): void
    {
        if (! empty($this->order)) {
            foreach ($this->order as $field => $direction) {
                $this->builder->orderBy($field, $direction);
            }
        }
    }

    /**
     * Check if the model is searchable.
     */
    protected function isSearchable(): bool
    {
        $model = $this->builder->getModel();

        return in_array(Searchable::class, class_uses_recursive($model));
    }

    /**
     * Check if the query is in search mode.
     */
    public function isSearch(): bool
    {
        return $this->search !== null;
    }
}
