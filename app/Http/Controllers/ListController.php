<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Models\Entry;
use App\Query\EntryFilter;
use App\Query\EntrySerializer;
use App\Query\Exceptions\QueryException;
use Illuminate\Http\JsonResponse;

class ListController extends Controller
{
    public function __construct(
        protected EntrySerializer $arrayConverter
    ) {}

    /**
     * @throws QueryException
     */
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
        $fields = $request->getFields();

        $paginatedResult = $filteredResult->simplePaginate(
            $perPage,
            ['*'],
            'page',
            $page,
            $this->arrayConverter,
            $fields,
            $filteredResult->isSearch()
        );

        return response()->json($paginatedResult->toArray());
    }
}
