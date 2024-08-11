<?php

namespace App\Query\JsonQueryBuilders;

use Illuminate\Database\Eloquent\Builder;

abstract class BaseQueryBuilder implements JsonQueryBuilder
{
    protected array $operatorMap = [
        '$eq' => '=',
        '$ne' => '!=',
        '$gt' => '>',
        '$gte' => '>=',
        '$lt' => '<',
        '$lte' => '<=',
        '$like' => 'LIKE',
        '$exists' => 'IS NOT NULL',
        '$notExists' => 'IS NULL',
    ];

    public function applyOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        match ($operator) {
            '$contains' => $this->applyContainsOperator($query, $jsonField, $jsonKey, $value),
            '$notContains' => $this->applyNotContainsOperator($query, $jsonField, $jsonKey, $value),
            '$in' => $this->applyInOperator($query, $jsonField, $jsonKey, $value),
            '$notIn' => $this->applyNotInOperator($query, $jsonField, $jsonKey, $value),
            '$exists' => $this->applyExistsOperator($query, $jsonField, $jsonKey, $value),
            '$notExists' => $this->applyNotExistsOperator($query, $jsonField, $jsonKey, $value),
            default => $this->applyDefaultOperator($query, $jsonField, $jsonKey, $operator, $value),
        };
    }

    public function applyJsonContains(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $this->applyContainsOperator($query, $jsonField, $jsonKey, $value);
    }

    protected function mapOperator(string $operator): string
    {
        return $this->operatorMap[$operator] ?? $operator;
    }

    abstract protected function applyContainsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void;

    abstract protected function applyNotContainsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void;

    abstract protected function applyInOperator(Builder $query, string $jsonField, string $jsonKey, $values): void;

    abstract protected function applyNotInOperator(Builder $query, string $jsonField, string $jsonKey, $values): void;

    abstract protected function applyExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void;

    abstract protected function applyNotExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void;

    abstract protected function applyDefaultOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, $value): void;

    abstract protected function getCastType($value): string;
}
