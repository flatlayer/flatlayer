<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Models\Entry;
use App\Query\EntryFilter;
use App\Query\EntrySerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ListController extends Controller
{
    public function __construct(
        protected EntrySerializer $arrayConverter
    ) {}

    public function index(ListRequest $request, ?string $type = null): JsonResponse
    {
        $query = Entry::query();

        if ($type) {
            $query->where('type', $type);

            if ($query->doesntExist()) {
                return response()->json(['error' => 'No items found for the specified type'], 404);
            }
        }

        $filter = new EntryFilter($query, $request->getFilter());
        $filteredResult = $filter->apply();

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $paginatedResult = $filteredResult->paginate($perPage, ['*'], 'page', $page);

        $fields = $request->getFields();

        $items = $filteredResult->isSearch()
            ? $paginatedResult->getCollection()->map(fn ($item) => ['item' => $item, 'relevance' => $item->relevance ?? null])
            : $paginatedResult->getCollection();

        $transformedItems = $items->map(function ($item) use ($fields, $filteredResult) {
            $transformedItem = $this->arrayConverter->toSummaryArray($filteredResult->isSearch() ? $item['item'] : $item, $fields);
            if ($filteredResult->isSearch()) {
                $transformedItem['relevance'] = $item['relevance'];
            }

            return $transformedItem;
        })->all();

        $transformedResults = new LengthAwarePaginator(
            $transformedItems,
            $paginatedResult->total(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($transformedResults);
    }
}
