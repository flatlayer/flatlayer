<?php

namespace App\Filter;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class QueryFilter
{
    protected Builder|Collection $builder;
    protected array $filters;
    protected ?string $search;

    public function __construct(Builder $builder, array $filters)
    {
        $this->builder = $builder;
        $this->filters = $filters;
        $this->search = $filters['$search'] ?? null;
        unset($this->filters['$search']);
    }

    public function apply(): Builder|Collection
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
                } elseif ($this->isFilterableField($field)) {
                    $this->applyFieldFilter($query, $field, $value);
                } else {
                    throw new InvalidArgumentException("Filtering by field '$field' is not allowed.");
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
            $this->builder = $modelClass::search(
                $this->search,
                rerank: true,
                builder: $this->builder
            );
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
