<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Services\ModelResolverService;
use App\Services\QueryFilter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListController extends Controller
{
    protected $modelResolver;

    public function __construct(ModelResolverService $modelResolver)
    {
        $this->modelResolver = $modelResolver;
    }

    public function index(ListRequest $request, $modelSlug)
    {
        $modelClass = $this->modelResolver->resolve($modelSlug);

        if (!$modelClass) {
            return response()->json(['error' => 'Invalid model'], 400);
        }

        // Use defaultSearchableQuery if available, otherwise use query
        $query = method_exists($modelClass, 'defaultSearchableQuery')
            ? $modelClass::defaultSearchableQuery()
            : $modelClass::query();

        $filter = new QueryFilter($query, $request->getFilter());
        $filteredResult = $filter->apply();

        $perPage = $request->input('per_page', 15);

        if ($filteredResult instanceof Builder) {
            $results = $filteredResult->paginate($perPage);
            $items = $results->items();
            $total = $results->total();
        } elseif ($filteredResult instanceof Collection) {
            $page = $request->input('page', 1);
            $items = $filteredResult->forPage($page, $perPage)->values();
            $total = $filteredResult->count();
        } else {
            return response()->json(['error' => 'Unexpected query result type'], 500);
        }

        // Transform the items using toSummaryArray if it exists
        $transformedItems = $this->transformItems($items);

        // Create a new LengthAwarePaginator with the transformed items
        $transformedResults = new LengthAwarePaginator(
            $transformedItems,
            $total,
            $perPage,
            LengthAwarePaginator::resolveCurrentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($transformedResults);
    }

    protected function transformItems($items)
    {
        return collect($items)->map(
            fn($item) => method_exists($item, 'toSummaryArray') ? $item->toSummaryArray() : $item->toArray()
        )->all();
    }
}
