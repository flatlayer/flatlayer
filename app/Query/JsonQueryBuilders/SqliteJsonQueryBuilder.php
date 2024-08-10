<?php

namespace App\Query\JsonQueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class SqliteJsonQueryBuilder implements JsonQueryBuilder
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
                $sqliteOperator = $this->mapOperator($operator);
                $castType = $this->getCastType($value);
                $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) {$sqliteOperator} ?", [$value]);
        }
    }

    public function applyExactMatch(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $castType = $this->getCastType($value);
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) = ?", [$value]);
    }

    public function applyJsonContains(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $this->applyContainsOperator($query, $jsonField, $jsonKey, $value);
    }

    protected function applyContainsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw("JSON_ARRAY_LENGTH(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) > 0 AND JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) = 'array' AND EXISTS (SELECT 1 FROM JSON_EACH(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) WHERE JSON_EACH.value = ?)", [$value]);
    }

    protected function applyNotContainsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw("NOT (JSON_ARRAY_LENGTH(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) > 0 AND JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) = 'array' AND EXISTS (SELECT 1 FROM JSON_EACH(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) WHERE JSON_EACH.value = ?))", [$value]);
    }

    protected function applyInOperator(Builder $query, string $jsonField, string $jsonKey, $values): void
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $castType = $this->getCastType($values[0]); // Assuming all values are of the same type
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) IN ({$placeholders})", $values);
    }

    protected function applyNotInOperator(Builder $query, string $jsonField, string $jsonKey, $values): void
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $castType = $this->getCastType($values[0]); // Assuming all values are of the same type
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) NOT IN ({$placeholders})", $values);
    }

    protected function applyExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        if ($value === true || $value === 'true' || $value === 1) {
            $query->whereRaw("JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) IS NOT NULL");
        } else {
            $query->whereRaw("JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) IS NULL");
        }
    }

    protected function applyNotExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        if ($value === true || $value === 'true' || $value === 1) {
            $query->whereRaw("JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) IS NULL");
        } else {
            $query->whereRaw("JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) IS NOT NULL");
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
            return 'NUMERIC';
        } elseif (is_bool($value)) {
            return 'INTEGER'; // SQLite doesn't have a boolean type, so we use INTEGER
        } else {
            return 'TEXT';
        }
    }
}
