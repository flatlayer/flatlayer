<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\ShowRequest;
use App\Models\Entry;
use App\Query\EntryFilter;
use App\Query\EntrySerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ContentController extends Controller
{
    /**
     * ContentController constructor.
     *
     * @param EntrySerializer $arrayConverter The serializer for converting entries to arrays
     */
    public function __construct(
        protected EntrySerializer $arrayConverter
    ) {}

    /**
     * List entries of a specific type.
     *
     * @param ListRequest $request The incoming request
     * @param string|null $type The type of entries to list
     * @return JsonResponse The JSON response containing the list of entries
     * @throws \App\Query\Exceptions\QueryException
     */
    public function index(ListRequest $request, ?string $type = null): JsonResponse
    {
        // ... (existing code remains unchanged)
    }

    /**
     * Show a single entry or multiple entries.
     *
     * @param ShowRequest $request The incoming request
     * @param string $type The type of the entry
     * @param string $slug The slug of the entry (for single entry requests)
     * @return JsonResponse The JSON response containing the entry or entries
     * @throws \Exception
     */
    public function show(ShowRequest $request, string $type, string $slug): JsonResponse
    {
        $contentItem = Entry::where('type', $type)
            ->where('slug', $slug)
            ->firstOrFail();

        $fields = $request->getFields();

        return response()->json(
            $this->arrayConverter->toDetailArray($contentItem, $fields)
        );
    }

    /**
     * Show multiple entries in a batch.
     *
     * @param ShowRequest $request The incoming request
     * @param string $type The type of the entries
     * @return JsonResponse The JSON response containing the entries
     * @throws \Exception
     */
    public function batch(ShowRequest $request, string $type): JsonResponse
    {
        $slugs = $request->getSlugs();

        if (empty($slugs)) {
            return response()->json(['error' => 'No valid slugs provided'], 400);
        }

        $query = Entry::where('type', $type)->whereIn('slug', $slugs);

        if ($query->doesntExist()) {
            return response()->json(['error' => 'No items found for the specified type and slugs'], 404);
        }

        $fields = $request->getFields();

        $items = $query->get()->map(function (Entry $item) use ($fields) {
            return $this->arrayConverter->toDetailArray($item, $fields);
        });

        // Ensure the order of items matches the order of requested slugs
        $orderedItems = collect($slugs)->map(function ($slug) use ($items) {
            return $items->firstWhere('slug', $slug);
        })->filter()->values();

        return response()->json(['data' => $orderedItems]);
    }
}
