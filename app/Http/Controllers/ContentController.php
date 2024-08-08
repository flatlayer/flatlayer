<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Query\EntrySerializer;
use App\Query\EntryFilter;
use App\Http\Requests\ListRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class ContentController extends Controller
{
    public function __construct(
        protected EntrySerializer $arrayConverter
    ) {}

    public function index(ListRequest $request, $type = null)
    {
        $query = Entry::query();

        if ($type) {
            $query->where('type', $type);

            // Check if any items exist for the given type
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

        $transformedItems = collect($paginatedResult->items())->map(
            fn($item) => $this->arrayConverter->toSummaryArray($item, $fields)
        )->all();

        $transformedResults = new LengthAwarePaginator(
            $transformedItems,
            $paginatedResult->total(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($transformedResults);
    }

    public function show(ListRequest $request, $type, $slug)
    {
        $contentItem = Entry::where('type', $type)
            ->where('slug', $slug)
            ->firstOrFail();

        $fields = $request->getFields();

        return response()->json(
            $this->arrayConverter->toDetailArray($contentItem, $fields)
        );
    }
}
