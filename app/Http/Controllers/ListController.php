<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Services\ModelResolverService;
use App\Services\QueryFilter;
use Illuminate\Pagination\LengthAwarePaginator;

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
        $filteredQuery = $filter->apply();

        $perPage = $request->input('per_page', 15);
        $results = $filteredQuery->paginate($perPage);

        // Transform the items using toSummaryArray if it exists
        $transformedItems = $this->transformItems($results->items());

        // Create a new LengthAwarePaginator with the transformed items
        $transformedResults = new LengthAwarePaginator(
            $transformedItems,
            $results->total(),
            $results->perPage(),
            $results->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($transformedResults);
    }

    protected function transformItems($items)
    {
        return array_map(function ($item) {
            if (method_exists($item, 'toSummaryArray')) {
                return $item->toSummaryArray();
            }
            return $item->toArray();
        }, $items);
    }
}
