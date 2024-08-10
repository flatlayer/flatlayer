<?php

namespace App\Query\JsonQueryBuilders;

use Illuminate\Database\Eloquent\Builder;

interface JsonQueryBuilder
{
    public function applyOperator(Builder $query, string $jsonField, string $jsonKey, string $operator, $value): void;

    public function applyExactMatch(Builder $query, string $jsonField, string $jsonKey, $value): void;

    public function applyJsonContains(Builder $query, string $jsonField, string $jsonKey, $value): void;
}
