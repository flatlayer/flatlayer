<?php

namespace App\Filter;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdvancedQueryFilter
{
    protected ?string $search;

    protected Collection|Builder $builder;

    public function __construct(Builder $builder, protected array $filters)
    {
        $this->builder = $builder;
        $this->search = $filters['$search'] ?? null;
        unset($this->filters['$search']);
    }

    public function apply(): Builder|Collection
    {
        $this->applyFilters($this->filters);
        $this->applySearch();
        $this->applyOrder();
        return $this->builder;
    }

    protected function applyFilters(array $filters, $operator = 'and'): void
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

    protected function applyFieldFilter($query, string $field, $value): void
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

    protected function applyJsonFieldFilter($query, string $field, $value): void
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

    protected function applyJsonOperator($query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        $databaseDriver = DB::getDriverName();

        if ($databaseDriver === 'pgsql') {
            $this->applyPostgresJsonOperator($query, $jsonField, $jsonKey, $operator, $value);
        } else {
            $this->applySqliteJsonOperator($query, $jsonField, $jsonKey, $operator, $value);
        }
    }

    protected function applyPostgresJsonOperator($query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        $jsonOperator = $this->mapJsonOperator($operator);
        $castType = is_numeric($value) ? '::numeric' : '';
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} {$jsonOperator} ?", [$value]);
    }

    protected function applySqliteJsonOperator($query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        $jsonOperator = $this->mapJsonOperator($operator);
        $query->whereRaw("JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') {$jsonOperator} ?", [$value]);
    }

    protected function applyJsonExactMatch($query, string $jsonField, string $jsonKey, $value): void
    {
        $databaseDriver = DB::getDriverName();

        if ($databaseDriver === 'pgsql') {
            $query->whereRaw("{$jsonField}->'{$jsonKey}' = ?", [$value]);
        } else {
            $query->whereRaw("JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') = ?", [$value]);
        }
    }

    protected function applyOperator($query, string $field, string $operator, $value): void
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

    protected function applyTagFilters($tags): void
    {
        if (is_array($tags) && !isset($tags['type'])) {
            $this->builder->withAnyTags($tags);
        } elseif (is_array($tags) && isset($tags['type']) && isset($tags['values'])) {
            $this->builder->withAnyTags($tags['values'], $tags['type']);
        }
    }

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

    protected function applyOrder(): void
    {
        if (!$this->isSearch() && !empty($this->order)) {
            foreach ($this->order as $field => $direction) {
                $this->builder->orderBy($field, $direction);
            }
        }
    }

    protected function isSearchable(): bool
    {
        $model = $this->builder->getModel();
        return in_array(Searchable::class, class_uses_recursive($model));
    }

    public function isSearch(): bool
    {
        return $this->search !== null;
    }
}
