<?php

namespace App\Http\Controllers;

use App\Filter\AdvancedQueryFilter;
use App\Http\Requests\ListRequest;
use App\Models\ContentItem;
use App\Filters\ContentItemArrayConverter;
use Illuminate\Pagination\LengthAwarePaginator;

class ContentItemListController extends Controller
{
    public function __construct(
        protected ContentItemArrayConverter $arrayConverter
    ) {}

    public function index(ListRequest $request, $type = null)
    {
        $query = ContentItem::query();

        if ($type) {
            $query->where('type', $type);
        }

        $filter = new AdvancedQueryFilter($query, $request->getFilter());
        $filteredResult = $filter->apply();

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $paginatedResult = $filteredResult->paginate($perPage, ['*'], 'page', $page);

        $fields = $request->getFields();
        $transformedItems = $this->transformItems($paginatedResult->items(), $fields);

        $transformedResults = new LengthAwarePaginator(
            $transformedItems,
            $paginatedResult->total(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($transformedResults);
    }

    protected function transformItems($items, array $fields)
    {
        return collect($items)->map(
            fn($item) => $this->arrayConverter->toSummaryArray($item, $fields)
        )->all();
    }
}