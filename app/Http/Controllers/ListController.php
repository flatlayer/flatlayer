<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Services\ModelResolverService;
use Illuminate\Database\Eloquent\Builder;
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

        $query = $modelClass::query();

        if ($request->has('query') && method_exists($modelClass, 'search')) {
            $searchQuery = $modelClass::search($request->input('query'));
            $query = $searchQuery->getQuery();
        }

        $this->applyFilters($query, $request->filters(), $modelClass);

        $perPage = $request->input('per_page', 15);
        $results = $query->paginate($perPage);

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

    protected function applyFilters(Builder $query, array $filters, string $modelClass)
    {
        foreach ($filters as $field => $value) {
            if ($field === 'tags') {
                $this->applyTagFilters($query, $value);
            } elseif ($this->isFilterableField($field, $modelClass)) {
                $this->applyFieldFilter($query, $field, $value);
            }
        }
    }

    protected function applyTagFilters(Builder $query, array $tags)
    {
        foreach ($tags as $type => $values) {
            $values = (array) $values;
            $query->whereHas('tags', function (Builder $query) use ($type, $values) {
                $query->where('type', $type === 'default' ? null : $type)
                    ->whereIn('slug', $values);
            });
        }
    }

    protected function applyFieldFilter(Builder $query, string $field, array $value)
    {
        if (count($value) === 2 && is_numeric($value[0])) {
            // Operator filter
            $query->where($field, $value[0], $value[1]);
        } else {
            // Simple filter
            $query->whereIn($field, $value);
        }
    }

    protected function isFilterableField(string $field, string $modelClass): bool
    {
        return in_array($field, $modelClass::$allowedFilters ?? []);
    }
}
