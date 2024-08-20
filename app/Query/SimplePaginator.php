<?php

namespace App\Query;

use Illuminate\Support\Collection;

class SimplePaginator
{
    public function __construct(
        protected readonly Collection $items,
        protected readonly int $total,
        protected readonly int $perPage,
        protected readonly int $currentPage,
        protected readonly EntrySerializer $arrayConverter,
        protected readonly array $fields = [],
        protected readonly bool $isSearch = false
    ) {}

    public function items(): Collection
    {
        return $this->items;
    }

    public function toArray(): array
    {
        $transformedItems = $this->items->map(function ($item) {
            $transformedItem = $this->arrayConverter->toSummaryArray($item, $this->fields);
            if ($this->isSearch) {
                $transformedItem['relevance'] = $item->relevance ?? null;
            }

            return $transformedItem;
        });

        return [
            'data' => $transformedItems->values()->all(),
            'pagination' => [
                'current_page' => $this->currentPage,
                'total_pages' => $this->calculateTotalPages(),
                'per_page' => $this->perPage,
            ],
        ];
    }

    protected function calculateTotalPages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }
}
