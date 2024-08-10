<?php

namespace App\Query\JsonQueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class SqliteJsonQueryBuilder implements JsonQueryBuilder
{
    public function applyOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, $value): void
    {
        $sqliteOperator = $this->mapOperator($operator);
        $castType = $this->getCastType($value);
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) {$sqliteOperator} ?", [$value]);
    }

    public function applyExactMatch(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $castType = $this->getCastType($value);
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) = ?", [$value]);
    }

    public function applyJsonContains(Builder $query, string $jsonField, string $jsonKey, $value): void
    {
        $castType = $this->getCastType($value);
        $query->whereRaw("CAST(JSON_EXTRACT({$jsonField}, '$.{$jsonKey}') AS {$castType}) = ?", [$value]);
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
