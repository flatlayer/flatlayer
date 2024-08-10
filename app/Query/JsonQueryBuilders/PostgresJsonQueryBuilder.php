<?php

namespace App\Query\JsonQueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class PostgresJsonQueryBuilder implements JsonQueryBuilder
{
    public function applyOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        switch ($operator) {
            case '$contains':
                $this->applyContainsOperator($query, $jsonField, $jsonKey, $value);
                break;
            case '$notContains':
                $this->applyNotContainsOperator($query, $jsonField, $jsonKey, $value);
                break;
            case '$in':
                $this->applyInOperator($query, $jsonField, $jsonKey, $value);
                break;
            case '$notIn':
                $this->applyNotInOperator($query, $jsonField, $jsonKey, $value);
                break;
            case '$exists':
                $this->applyExistsOperator($query, $jsonField, $jsonKey, $value);
                break;
            case '$notExists':
                $this->applyNotExistsOperator($query, $jsonField, $jsonKey, $value);
                break;
            default:
                $postgresOperator = $this->mapOperator($operator);
                $castType = $this->getCastType($value);
                $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} {$postgresOperator} ?", [$value]);
        }
    }

    public function applyExactMatch(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $castType = $this->getCastType($value);
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} = ?", [$value]);
    }

    public function applyJsonContains(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $this->applyContainsOperator($query, $jsonField, $jsonKey, $value);
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
        $castType = $this->getCastType($values[0]); // Assuming all values are of the same type
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} IN ({$placeholders})", $values);
    }

    protected function applyNotInOperator(Builder $query, string $jsonField, string $jsonKey, $values): void
    {
        $castType = $this->getCastType($values[0]); // Assuming all values are of the same type
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw("({$jsonField}->'{$jsonKey}'){$castType} NOT IN ({$placeholders})", $values);
    }

    protected function applyExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        if ($value === true || $value === 'true' || $value === 1) {
            $query->whereRaw("({$jsonField}->'{$jsonKey}') IS NOT NULL");
        } else {
            $query->whereRaw("({$jsonField}->'{$jsonKey}') IS NULL");
        }
    }

    protected function applyNotExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        if ($value === true || $value === 'true' || $value === 1) {
            $query->whereRaw("({$jsonField}->'{$jsonKey}') IS NULL");
        } else {
            $query->whereRaw("({$jsonField}->'{$jsonKey}') IS NOT NULL");
        }
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

    protected function getCastType($value): string
    {
        if (is_numeric($value)) {
            return '::numeric';
        } elseif (is_bool($value)) {
            return '::boolean';
        } else {
            return '::text';
        }
    }
}
