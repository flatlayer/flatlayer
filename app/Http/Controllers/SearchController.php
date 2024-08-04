<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;

class SearchController extends Controller
{
    public function search(SearchRequest $request)
    {
        $modelClass = $request->getModelClass();

        if (!$modelClass || !method_exists($modelClass, 'search')) {
            return response()->json(['error' => 'Invalid model or model is not searchable'], 400);
        }

        $perPage = $request->input('per_page', 15);
        $searchQuery = $modelClass::search($request->input('query'));

        // Apply filters
        foreach ($request->filters() as $field => $value) {
            $searchQuery->where($field, $value);
        }

        $results = $searchQuery->paginate($perPage);

        return response()->json($results);
    }
}
