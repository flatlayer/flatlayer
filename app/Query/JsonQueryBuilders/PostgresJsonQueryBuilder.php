<?php

namespace App\Query\JsonQueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class PostgresJsonQueryBuilder implements JsonQueryBuilder
{
    public function applyOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        $postgresOperator = $this->mapOperator($operator);
        $castType = is_numeric($value) ? '::numeric' : '';
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} {$postgresOperator} ?", [$value]);
    }

    public function applyExactMatch(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw("{$jsonField}->'{$jsonKey}' = ?", [$value]);
    }

    public function applyJsonContains(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereJsonContains($jsonField, [$jsonKey => $value]);
    }

    protected function mapOperator(string $operator): string
    {
        $map = [
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
        return $map[$operator] ?? $operator;
    }
}
