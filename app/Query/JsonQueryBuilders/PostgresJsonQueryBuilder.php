<?php

namespace App\Query\JsonQueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class PostgresJsonQueryBuilder extends BaseQueryBuilder
{
    public function applyExactMatch(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $castType = $this->getCastType($value);
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} = ?", [$value]);
    }

    protected function applyContainsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw("jsonb_typeof({$jsonField}->'{$jsonKey}') = 'array' AND {$jsonField}->'{$jsonKey}' @> ?::jsonb", [json_encode([$value])]);
    }

    protected function applyNotContainsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw("NOT (jsonb_typeof({$jsonField}->'{$jsonKey}') = 'array' AND {$jsonField}->'{$jsonKey}' @> ?::jsonb)", [json_encode([$value])]);
    }

    protected function applyInOperator(Builder $query, string $jsonField, string $jsonKey, $values): void
    {
        $castType = $this->getCastType($values[0]);
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} IN ({$placeholders})", $values);
    }

    protected function applyNotInOperator(Builder $query, string $jsonField, string $jsonKey, $values): void
    {
        $castType = $this->getCastType($values[0]);
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} NOT IN ({$placeholders})", $values);
    }

    protected function applyExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw($value ? "({$jsonField}->'{$jsonKey}') IS NOT NULL" : "({$jsonField}->'{$jsonKey}') IS NULL");
    }

    protected function applyNotExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw($value ? "({$jsonField}->'{$jsonKey}') IS NULL" : "({$jsonField}->'{$jsonKey}') IS NOT NULL");
    }

    protected function applyDefaultOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        $postgresOperator = $this->mapOperator($operator);
        $castType = $this->getCastType($value);
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} {$postgresOperator} ?", [$value]);
    }

    protected function getCastType($value): string
    {
        return match (true) {
            is_numeric($value) => '::numeric',
            is_bool($value) => '::boolean',
            default => '::text',
        };
    }
}
