<?php

namespace App\Query;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntryFilter
{
    protected ?string $search;

    protected Collection|Builder $builder;

    public function __construct(Builder $builder, protected array $filters)
    {
        $this->builder = $builder;
        $this->search = $filters['$search'] ?? null;
        unset($this->filters['$search']);
    }

    /**
     * Apply all filters and search to the query builder.
     */
    public function apply(): Builder|Collection
    {
        $this->applyFilters($this->filters);
        $this->applySearch();
        $this->applyOrder();
        return $this->builder;
    }

    /**
     * Apply filters recursively to the query builder.
     *
     * @param string $operator The logical operator to use ('and' or 'or')
     */
    protected function applyFilters(array $filters, string $operator = 'and'): void
    {
        $method = $operator === 'or' ? 'orWhere' : 'where';

        $this->builder->$method(function ($query) use ($filters) {
            foreach ($filters as $field => $value) {
                if ($field === '$and') {
                    $query->where(function ($q) use ($value) {
                        foreach ($value as $andCondition) {
                            $this->applyFilters($andCondition, 'and');
                        }
                    });
                } elseif ($field === '$or') {
                    $query->orWhere(function ($q) use ($value) {
                        foreach ($value as $orCondition) {
                            $this->applyFilters($orCondition, 'or');
                        }
                    });
                } elseif ($field === '$tags') {
                    $this->applyTagFilters($value);
                } else {
                    $this->applyFieldFilter($query, $field, $value);
                }
            }
        });
    }

    /**
     * Apply a filter to a specific field.
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
     */
    protected function applyJsonOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, mixed $value): void
    {
        $databaseDriver = DB::getDriverName();

        if ($databaseDriver === 'pgsql') {
            $this->applyPostgresJsonOperator($query, $jsonField, $jsonKey, $operator, $value);
        } else {
            $this->applySqliteJsonOperator($query, $jsonField, $jsonKey, $operator, $value);
        }
    }

    /**
     * Apply a PostgreSQL JSON operator.
     */
    protected function applyPostgresJsonOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, mixed $value): void
    {
        $jsonOperator = $this->mapJsonOperator($operator);
        $castType = is_numeric($value) ? '::numeric' : '';
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} {$jsonOperator} ?", [$value]);
    }

    /**
     * Apply a SQLite JSON operator.
     */
    protected function applySqliteJsonOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, mixed $value): void
    {
        $jsonOperator = $this->mapJsonOperator($operator);
        $query->whereRaw("JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') {$jsonOperator} ?", [$value]);
    }

    /**
     * Apply an exact match filter to a JSON field.
     */
    protected function applyJsonExactMatch(Builder $query, string $jsonField, string $jsonKey, mixed $value): void
    {
        $databaseDriver = DB::getDriverName();

        if ($databaseDriver === 'pgsql') {
            $query->whereRaw("{$jsonField}->'{$jsonKey}' = ?", [$value]);
        } else {
            $query->whereRaw("JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') = ?", [$value]);
        }
    }

    /**
     * Apply an operator to a field.
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
            default:
                $query->where($field, $value);
        }
    }

    /**
     * Map a JSON operator to its SQL equivalent.
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
            default:
                return '=';
        }
    }

    /**
     * Apply tag filters to the query.
     */
    protected function applyTagFilters(array|string $tags): void
    {
        if (is_array($tags) && !isset($tags['type'])) {
            $this->builder->withAnyTags($tags);
        } elseif (is_array($tags) && isset($tags['type']) && isset($tags['values'])) {
            $this->builder->withAnyTags($tags['values'], $tags['type']);
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
