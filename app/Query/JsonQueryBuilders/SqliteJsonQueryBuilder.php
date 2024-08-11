<?php

namespace App\Query\JsonQueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class SqliteJsonQueryBuilder extends BaseQueryBuilder
{
    public function applyExactMatch(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $castType = $this->getCastType($value);
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) = ?", [$value]);
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
        $castType = $this->getCastType($values[0]);
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) IN ({$placeholders})", $values);
    }

    protected function applyNotInOperator(Builder $query, string $jsonField, string $jsonKey, $values): void
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $castType = $this->getCastType($values[0]);
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) NOT IN ({$placeholders})", $values);
    }

    protected function applyExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw($value ? "JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) IS NOT NULL" : "JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) IS NULL");
    }

    protected function applyNotExistsOperator(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $query->whereRaw($value ? "JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) IS NULL" : "JSON_TYPE(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}')) IS NOT NULL");
    }

    protected function applyDefaultOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        $sqliteOperator = $this->mapOperator($operator);
        $castType = $this->getCastType($value);
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) {$sqliteOperator} ?", [$value]);
    }

    protected function getCastType($value): string
    {
        return match (true) {
            is_numeric($value) => 'NUMERIC',
            is_bool($value) => 'INTEGER',
            default => 'TEXT',
        };
    }
}
