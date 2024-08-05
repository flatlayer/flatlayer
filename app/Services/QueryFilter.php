<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\Searchable;

class QueryFilter
{
    protected $builder;
    protected $filters;
    protected $search;

    public function __construct(Builder $builder, array $filters)
    {
        $this->builder = $builder;
        $this->filters = $filters;
        $this->search = $filters['$search'] ?? null;
    }

    public function apply(): Builder
    {
        $this->applyFilters($this->filters);
        $this->applySearch();
        return $this->builder;
    }

    protected function applyFilters(array $filters, $operator = 'and'): void
    {
        $method = $operator === 'or' ? 'orWhere' : 'where';

        $this->builder->$method(function ($query) use ($filters) {
            foreach ($filters as $field => $value) {
                if ($field === '$and') {
                    $query->where(function ($q) use ($value) {
                        $this->applyFilters($value, 'and');
                    });
                } elseif ($field === '$or') {
                    $query->where(function ($q) use ($value) {
                        $this->applyFilters($value, 'or');
                    });
                } elseif ($field === '$tags') {
                    $this->applyTagFilters($value);
                } elseif ($field !== '$search' && $this->isFilterableField($field)) {
                    $this->applyFieldFilter($query, $field, $value);
                }
            }
        });
    }

    protected function applyFieldFilter($query, string $field, $value): void
    {
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

    protected function applyTagFilters($tags): void
    {
        if (is_array($tags) && !isset($tags['type'])) {
            $this->builder->withAnyTags($tags);
        } elseif (is_array($tags) && isset($tags['type']) && isset($tags['values'])) {
            $this->builder->withAnyTags($tags['values'], $tags['type']);
        }
    }

    protected function isFilterableField(string $field): bool
    {
        $model = $this->builder->getModel();
        return in_array($field, $model::$allowedFilters ?? []);
    }

    protected function applySearch(): void
    {
        if ($this->search && $this->isSearchable()) {
            $modelClass = get_class($this->builder->getModel());
            $this->builder = $modelClass::search($this->search, null, true, $this->builder);
        }
    }

    protected function isSearchable(): bool
    {
        $model = $this->builder->getModel();
        return in_array(Searchable::class, class_uses_recursive($model));
    }
}
