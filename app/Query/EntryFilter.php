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
     * Create a new instance of the EntryFilter.
     *
     * @param  Builder  $builder  The query builder instance
     * @param  array  $filters  The filters to apply
     *
     * @throws \Exception If the database driver is not supported
     */
    public function __construct(Builder $builder, protected array $filters)
    {
        $this->builder = $builder;
        $this->search = $filters['$search'] ?? null;
        $this->order = $filters['$order'] ?? [];
        unset($this->filters['$search'], $this->filters['$order']);
        $this->jsonQueryBuilder = $this->createJsonQueryBuilder();
    }

    /**
     * Create a new JSON query builder instance.
     *
     * @throws \Exception
     */
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
                    '$hierarchy' => $this->applyHierarchyFilter($value),
                    default => $this->applyFieldFilter($subQuery, $field, $value)
                };
            }
        });
    }

    /**
     * Apply OR conditions to the query.
     */
    protected function applyOrConditions(array $conditions): void
    {
        $this->builder->where(function ($query) use ($conditions) {
            foreach ($conditions as $condition) {
                $query->orWhere(function ($subQuery) use ($condition) {
                    foreach ($condition as $field => $value) {
                        if ($field === '$and') {
                            $this->applyAndConditions($value, $subQuery);
                        } elseif ($field === '$or') {
                            $this->applyOrConditions($value);
                        } else {
                            $this->applyFieldFilter($subQuery, $field, $value);
                        }
                    }
                });
            }
        });
    }

    /**
     * Apply AND conditions to the query.
     */
    protected function applyAndConditions(array $conditions, ?Builder $query = null): void
    {
        $query = $query ?? $this->builder;

        $query->where(function ($subQuery) use ($conditions) {
            foreach ($conditions as $condition) {
                foreach ($condition as $field => $value) {
                    if ($field === '$or') {
                        $this->applyOrConditions($value);
                    } else {
                        $this->applyFieldFilter($subQuery, $field, $value);
                    }
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
        } elseif ($field === '$hierarchy') {
            $this->applyHierarchyFilter($value);
        } elseif (Str::contains($field, '.')) {
            $this->applyJsonFieldFilter($query, $field, $value);
        } elseif (is_array($value)) {
            foreach ($value as $operator => $operand) {
                switch ($operator) {
                    case '$startsWith':
                        $query->where($field, 'like', $operand.'%');
                        break;
                    case '$endsWith':
                        $query->where($field, 'like', '%'.$operand);
                        break;
                    case '$notStartsWith':
                        $query->where($field, 'not like', $operand.'%');
                        break;
                    case '$notEndsWith':
                        $query->where($field, 'not like', '%'.$operand);
                        break;
                    default:
                        $this->applyOperator($query, $field, $operator, $operand);
                }
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
            '$startsWith' => $query->where($field, 'like', $value.'%'),
            '$endsWith' => $query->where($field, 'like', '%'.$value),
            '$contains' => $query->where($field, 'like', '%'.$value.'%'),
            '$notStartsWith' => $query->where($field, 'not like', $value.'%'),
            '$notEndsWith' => $query->where($field, 'not like', '%'.$value),
            '$isChildOf' => $query->where($field, 'like', $value.'/%')
                ->whereRaw("replace($field, ?, '') not like '%/%'", [$value.'/']),
            '$isDescendantOf' => $query->where($field, 'like', $value.'/%'),
            '$isSiblingOf' => $query->where(function ($q) use ($field, $value) {
                if (! str_contains($value, '/')) {
                    $q->whereRaw("$field not like '%/%'");
                } else {
                    $parent = substr($value, 0, strrpos($value, '/'));
                    $q->where($field, 'like', $parent.'/%')
                        ->whereRaw("replace($field, ?, '') not like '%/%'", [$parent.'/'])
                        ->where($field, '!=', $value);
                }
            }),
            '$hasParent' => $query->where(function ($q) use ($field, $value) {
                if ($value === '') {
                    $q->whereRaw("$field not like '%/%'");
                } else {
                    $q->where($field, 'like', $value.'/%')
                        ->whereRaw("replace($field, ?, '') not like '%/%'", [$value.'/']);
                }
            }),
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
     * Apply hierarchy filter to the query.
     *
     * @param  array  $conditions  The hierarchy conditions to apply
     *
     * @throws InvalidFilterException If an invalid hierarchy relationship is specified
     */
    protected function applyHierarchyFilter(array $conditions): void
    {
        foreach ($conditions as $relationship => $value) {
            match ($relationship) {
                'descendants' => $this->builder->where('slug', 'like', $value.'/%'),
                'ancestors' => $this->builder->where(function ($q) use ($value) {
                    $parts = explode('/', $value);
                    array_pop($parts);
                    $currentPath = '';
                    foreach ($parts as $part) {
                        $currentPath = $currentPath ? "$currentPath/$part" : $part;
                        $q->orWhere('slug', $currentPath);
                    }
                }),
                'siblings' => $this->builder->where(function ($q) use ($value) {
                    if (! str_contains($value, '/')) {
                        $q->whereRaw("slug not like '%/%'")
                            ->where('slug', '!=', $value);
                    } else {
                        $parent = substr($value, 0, strrpos($value, '/'));
                        $q->where('slug', 'like', $parent.'/%')
                            ->whereRaw("replace(slug, ?, '') not like '%/%'", [$parent.'/'])
                            ->where('slug', '!=', $value);
                    }
                }),
                default => throw new InvalidFilterException("Invalid hierarchy relationship: $relationship")
            };
        }
    }

    /**
     * Apply tag filters to the query.
     *
     * @param  array  $tags  The tags to filter by
     * @param  Builder|null  $query  Optional query builder instance
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
     *
     * @return Collection The search results
     */
    protected function applySearch(): Collection
    {
        $modelClass = get_class($this->builder->getModel());

        return $modelClass::search(
            $this->search,
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
     *
     * @return bool True if the model uses the Searchable trait
     */
    protected function isSearchable(): bool
    {
        $model = $this->builder->getModel();

        return in_array(Searchable::class, class_uses_recursive($model));
    }

    /**
     * Check if the query is in search mode.
     *
     * @return bool True if a search query is present
     */
    public function isSearch(): bool
    {
        return $this->search !== null;
    }
}
