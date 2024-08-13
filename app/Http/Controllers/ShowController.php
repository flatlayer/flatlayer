<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowRequest;
use App\Models\Entry;
use App\Query\EntrySerializer;
use Illuminate\Http\JsonResponse;

class ShowController extends Controller
{
    public function __construct(
        protected EntrySerializer $arrayConverter
    ) {}

    public function show(ShowRequest $request, string $type, string $slug): JsonResponse
    {
        $contentItem = Entry::where('type', $type)
            ->where('slug', $slug)
            ->first();

        if (!$contentItem) {
            return response()->json(['error' => 'No item found for the specified type and slug'], 404);
        }

        $fields = $request->getFields();

        return response()->json(
            $this->arrayConverter->toDetailArray($contentItem, $fields)
        );
    }

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

        $slugOrder = collect($slugs)->flip()->map(fn ($index) => $index);

        $items = $query->get()
            ->sortBy(fn ($item) => $slugOrder[$item->slug] ?? PHP_INT_MAX)
            ->values()
            ->map(fn (Entry $item) => $this->arrayConverter->toDetailArray($item, $fields));

        return response()->json(['data' => $items]);
    }
}
