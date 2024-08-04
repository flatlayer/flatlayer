<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Tags\Tag;

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

        $this->applyFilters($searchQuery, $request->filters(), $modelClass);

        $results = $searchQuery->paginate($perPage);

        return response()->json($results);
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

    protected function applyFieldFilter(Builder $query, string $field, $value)
    {
        if (is_array($value) && count($value) === 2) {
            $operator = $value[0];
            $filterValue = $value[1];
            $query->where($field, $operator, $filterValue);
        } else {
            $query->where($field, $value);
        }
    }

    protected function isFilterableField(string $field, string $modelClass): bool
    {
        return in_array($field, $modelClass::$allowedFilters ?? []);
    }
}
