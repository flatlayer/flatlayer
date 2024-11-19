<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchShowRequest;
use App\Http\Requests\ShowRequest;
use App\Models\Entry;
use App\Query\EntrySerializer;
use App\Support\Path;
use Illuminate\Http\JsonResponse;

class ShowController extends Controller
{
    public function __construct(
        protected EntrySerializer $arrayConverter
    ) {}

    /**
     * Show a single content entry.
     */
    public function show(ShowRequest $request, string $type, string $slug): JsonResponse
    {
        // Normalize the slug using Path class - this now strips any /index
        $slug = Path::toSlug($slug);

        // Get the entry
        $contentItem = Entry::where('type', $type)
            ->where('slug', $slug)
            ->first();

        if (! $contentItem) {
            return response()->json(['error' => 'No item found for the specified type and slug'], 404);
        }

        $fields = $request->getFields();
        $result = $this->arrayConverter->toDetailArray($contentItem, $fields);

        // Add hierarchical navigation data if requested
        if ($request->includes('navigation')) {
            $result['navigation'] = [
                'ancestors' => $contentItem->ancestors()
                    ->map(fn ($entry) => [
                        'title' => $entry->title,
                        'slug' => $entry->slug,
                    ]),
                'siblings' => $contentItem->siblings()
                    ->map(fn ($entry) => [
                        'title' => $entry->title,
                        'slug' => $entry->slug,
                    ]),
                'children' => $contentItem->children()
                    ->map(fn ($entry) => [
                        'title' => $entry->title,
                        'slug' => $entry->slug,
                    ]),
            ];

            // For index pages, include parent navigation
            if ($contentItem->is_index) {
                $parent = $contentItem->parent();
                if ($parent) {
                    $result['navigation']['parent'] = [
                        'title' => $parent->title,
                        'slug' => $parent->slug,
                        'is_index' => $parent->is_index,
                    ];
                }
            }
        }

        return response()->json($result);
    }

    /**
     * Show multiple content entries in a single request.
     */
    public function batch(BatchShowRequest $request, string $type): JsonResponse
    {
        $slugs = $request->getSlugs();

        if (empty($slugs)) {
            return response()->json(['error' => 'No valid slugs provided'], 400);
        }

        // Process and normalize all slugs
        $processedSlugs = array_map(function ($slug) {
            return Path::toSlug($slug);
        }, $slugs);

        // Find all requested entries
        $items = Entry::where('type', $type)
            ->whereIn('slug', array_unique($processedSlugs))
            ->get();

        // If we haven't found all requested slugs, return 404
        $foundSlugs = $items->pluck('slug')->toArray();
        $missingSlugs = array_diff($processedSlugs, $foundSlugs);
        if (! empty($missingSlugs)) {
            return response()->json(['error' => 'No items found for the specified type and slugs'], 404);
        }

        $fields = $request->getFields();
        $slugOrder = collect($slugs)->flip();

        $result = $items->map(function (Entry $item) use ($fields) {
            return $this->arrayConverter->toDetailArray($item, $fields);
        })
            ->sortBy(fn ($item) => $slugOrder[$item['slug']] ?? PHP_INT_MAX)
            ->values();

        return response()->json(['data' => $result]);
    }
}
