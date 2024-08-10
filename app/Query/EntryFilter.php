<?php

namespace App\Query;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Query\Exceptions\InvalidFilterException;
use App\Query\Exceptions\QueryException;

class EntryFilter
{
    protected ?string $search;
    protected Collection|Builder $builder;
    protected array $order = [];

    public function __construct(Builder $builder, protected array $filters)
    {
        $this->builder = $builder;
        $this->search = $filters['$search'] ?? null;
        $this->order = $filters['$order'] ?? [];
        unset($this->filters['$search'], $this->filters['$order']);
    }

    /**
     * Apply all filters and search to the query builder.
     *
     * @throws QueryException
     */
    public function apply(): Builder|Collection
    {
        try {
            $this->applyFilters($this->filters);
            $this->applySearch();
            $this->applyOrder();
            return $this->builder;
        } catch (\Exception $e) {
            throw new QueryException("Error applying filters: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Apply filters recursively to the query builder.
     *
     * @param array $filters
     * @param string $operator
     * @throws InvalidFilterException
     */
    protected function applyFilters(array $filters, string $operator = 'and'): void
    {
        $method = $operator === 'or' ? 'orWhere' : 'where';

        $this->builder->$method(function ($query) use ($filters) {
            foreach ($filters as $field => $value) {
                switch ($field) {
                    case '$and':
                        $query->where(function ($q) use ($value) {
                            foreach ($value as $andCondition) {
                                $this->applyFilters($andCondition, 'and');
                            }
                        });
                        break;
                    case '$or':
                        $query->orWhere(function ($q) use ($value) {
                            foreach ($value as $orCondition) {
                                $this->applyFilters($orCondition, 'or');
                            }
                        });
                        break;
                    case '$tags':
                        $this->applyTagFilters($value);
                        break;
                    default:
                        $this->applyFieldFilter($query, $field, $value);
                }
            }
        });
    }

    /**
     * Apply a filter to a specific field.
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @throws InvalidFilterException
     */
    protected function applyFieldFilter(Builder $query, string $field, mixed $value): void
    {
        if (Str::contains($field, '.')) {
            $this->applyJsonFieldFilter($query, $field, $value);
        } else {
            if (is_array($value)) {
                if (isset($value['$in'])) {
                    $query->whereIn($field, $value['$in']);
                } elseif (isset($value['$exists'])) {
                    $method = $value['$exists'] ? 'whereNotNull' : 'whereNull';
                    $query->$method($field);
                } else {
                    foreach ($value as $operator => $operand) {
                        $this->applyOperator($query, $field, $operator, $operand);
                    }
                }
            } else {
                $query->where($field, $value);
            }
        }
    }

    /**
     * Apply a filter to a JSON field.
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @throws InvalidFilterException
     */
    protected function applyJsonFieldFilter(Builder $query, string $field, mixed $value): void
    {
        [$jsonField, $jsonKey] = explode('.', $field, 2);

        if (is_array($value)) {
            foreach ($value as $operator => $operand) {
                $this->applyJsonOperator($query, $jsonField, $jsonKey, $operator, $operand);
            }
        } else {
            $this->applyJsonExactMatch($query, $jsonField, $jsonKey, $value);
        }
    }

    /**
     * Apply a JSON operator to a field.
     *
     * @param Builder $query
     * @param string $jsonField
     * @param string $jsonKey
     * @param string $operator
     * @param mixed $value
     * @throws InvalidFilterException
     */
    protected function applyJsonOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, mixed $value): void
    {
        $databaseDriver = DB::getDriverName();

        if ($databaseDriver === 'pgsql') {
            $this->applyPostgresJsonOperator($query, $jsonField, $jsonKey, $operator, $value);
        } elseif ($databaseDriver === 'sqlite') {
            $this->applySqliteJsonOperator($query, $jsonField, $jsonKey, $operator, $value);
        } else {
            throw new InvalidFilterException("Unsupported database driver for JSON operations: $databaseDriver");
        }
    }

    /**
     * Apply a PostgreSQL JSON operator.
     *
     * @param Builder $query
     * @param string $jsonField
     * @param string $jsonKey
     * @param string $operator
     * @param mixed $value
     */
    protected function applyPostgresJsonOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, mixed $value): void
    {
        $jsonOperator = $this->mapJsonOperator($operator);
        $castType = is_numeric($value) ? '::numeric' : '';
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} {$jsonOperator} ?", [$value]);
    }

    /**
     * Apply a SQLite JSON operator.
     *
     * @param Builder $query
     * @param string $jsonField
     * @param string $jsonKey
     * @param string $operator
     * @param mixed $value
     */
    protected function applySqliteJsonOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, mixed $value): void
    {
        $jsonOperator = $this->mapJsonOperator($operator);
        $query->whereRaw("JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') {$jsonOperator} ?", [$value]);
    }

    /**
     * Apply an exact match filter to a JSON field.
     *
     * @param Builder $query
     * @param string $jsonField
     * @param string $jsonKey
     * @param mixed $value
     * @throws InvalidFilterException
     */
    protected function applyJsonExactMatch(Builder $query, string $jsonField, string $jsonKey, mixed $value): void
    {
        $databaseDriver = DB::getDriverName();

        if ($databaseDriver === 'pgsql') {
            $query->whereRaw("{$jsonField}->'{$jsonKey}' = ?", [$value]);
        } elseif ($databaseDriver === 'sqlite') {
            $query->whereRaw("JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') = ?", [$value]);
        } else {
            throw new InvalidFilterException("Unsupported database driver for JSON operations: $databaseDriver");
        }
    }

    /**
     * Apply an operator to a field.
     *
     * @param Builder $query
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @throws InvalidFilterException
     */
    protected function applyOperator(Builder $query, string $field, string $operator, mixed $value): void
    {
        switch ($operator) {
            case '$gt':
                $query->where($field, '>', $value);
                break;
            case '$gte':
                $query->where($field, '>=', $value);
                break;
            case '$lt':
                $query->where($field, '<', $value);
                break;
            case '$lte':
                $query->where($field, '<=', $value);
                break;
            case '$ne':
                $query->where($field, '!=', $value);
                break;
            case '$like':
                $query->where($field, 'LIKE', $value);
                break;
            case '$exists':
                if ($value === true || $value === 'true' || $value === 1) {
                    $query->whereNotNull($field);
                } elseif ($value === false || $value === 'false' || $value === 0) {
                    $query->whereNull($field);
                } else {
                    throw new InvalidFilterException("Invalid value for \$exists operator: $value");
                }
                break;
            case '$in':
                if (!is_array($value)) {
                    throw new InvalidFilterException("Value for \$in operator must be an array");
                }
                $query->whereIn($field, $value);
                break;
            case '$notIn':
                if (!is_array($value)) {
                    throw new InvalidFilterException("Value for \$notIn operator must be an array");
                }
                $query->whereNotIn($field, $value);
                break;
            case '$between':
                if (!is_array($value) || count($value) !== 2) {
                    throw new InvalidFilterException("Value for \$between operator must be an array with exactly two elements");
                }
                $query->whereBetween($field, $value);
                break;
            case '$notBetween':
                if (!is_array($value) || count($value) !== 2) {
                    throw new InvalidFilterException("Value for \$notBetween operator must be an array with exactly two elements");
                }
                $query->whereNotBetween($field, $value);
                break;
            case '$null':
                $query->whereNull($field);
                break;
            case '$notNull':
                $query->whereNotNull($field);
                break;
            default:
                if (empty($operator)) {
                    $query->where($field, $value);
                } else {
                    throw new InvalidFilterException("Invalid operator: $operator");
                }
        }
    }

    /**
     * Map a JSON operator to its SQL equivalent.
     *
     * @param string $operator
     * @return string
     * @throws InvalidFilterException
     */
    protected function mapJsonOperator(string $operator): string
    {
        switch ($operator) {
            case '$gt':
                return '>';
            case '$gte':
                return '>=';
            case '$lt':
                return '<';
            case '$lte':
                return '<=';
            case '$ne':
                return '!=';
            case '$like':
                return 'LIKE';
            case '$exists':
                return 'IS NOT NULL';
            case '$notExists':
                return 'IS NULL';
            case '$in':
                return 'IN';
            case '$notIn':
                return 'NOT IN';
            case '':
                return '=';
            default:
                throw new InvalidFilterException("Invalid JSON operator: $operator");
        }
    }

    /**
     * Apply tag filters to the query.
     *
     * @param array|string $tags The tags to filter by.
     * @throws InvalidFilterException
     */
    protected function applyTagFilters(array|string $tags): void
    {
        if (is_array($tags) && !isset($tags['type'])) {
            $this->builder->withAnyTags($tags);
        } elseif (is_array($tags) && isset($tags['type']) && isset($tags['values'])) {
            $this->builder->withAnyTags($tags['values'], $tags['type']);
        } else {
            throw new InvalidFilterException("Invalid tag filter format");
        }
    }

    /**
     * Apply search to the query if the model is searchable.
     */
    protected function applySearch(): void
    {
        if ($this->search && $this->isSearchable()) {
            $modelClass = get_class($this->builder->getModel());
            $this->builder = $modelClass::search(
                $this->search,
                rerank: true,
                builder: $this->builder
            );
        }
    }

    /**
     * Apply ordering to the query if not in search mode.
     */
    protected function applyOrder(): void
    {
        if (!$this->isSearch() && !empty($this->order)) {
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
