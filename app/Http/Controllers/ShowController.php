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
     *
     * @param  ShowRequest  $request  The validated request
     * @param  string  $type  Content type (e.g., 'post', 'doc')
     * @param  string  $slug  Content path/identifier
     *
     * @throws \Exception
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

        // Get navigation fields
        $navFields = $request->getNavigationFields();

        // Add hierarchical structure if requested
        if ($request->includes('hierarchy')) {
            $result['hierarchy'] = [
                'ancestors' => $contentItem->ancestors()
                    ->map(fn ($entry) => $this->arrayConverter->toSummaryArray($entry, $navFields)),
                'siblings' => $contentItem->siblings()
                    ->map(fn ($entry) => $this->arrayConverter->toSummaryArray($entry, $navFields)),
                'children' => $contentItem->children()
                    ->map(fn ($entry) => $this->arrayConverter->toSummaryArray($entry, $navFields)),
            ];

            if ($contentItem->is_index) {
                $parent = $contentItem->parent();
                if ($parent) {
                    $result['hierarchy']['parent'] = $this->arrayConverter->toSummaryArray($parent, $navFields);
                }
            }
        }

        // Add structural sequence navigation if requested
        if ($request->includes('sequence')) {
            $navigation = $contentItem->getNavigation('hierarchical');
            $result['sequence'] = [
                'previous' => $navigation['previous'] ?
                    $this->arrayConverter->toSummaryArray($navigation['previous'], $navFields) : null,
                'next' => $navigation['next'] ?
                    $this->arrayConverter->toSummaryArray($navigation['next'], $navFields) : null,
                'position' => $navigation['position'],
            ];
        }

        // Add chronological timeline navigation if requested
        if ($request->includes('timeline')) {
            $navigation = $contentItem->getNavigation('chronological');
            $result['timeline'] = [
                'previous' => $navigation['previous'] ?
                    $this->arrayConverter->toSummaryArray($navigation['previous'], $navFields) : null,
                'next' => $navigation['next'] ?
                    $this->arrayConverter->toSummaryArray($navigation['next'], $navFields) : null,
                'position' => $navigation['position'],
            ];
        }

        return response()->json($result);
    }

    /**
     * Show multiple content entries in a single request.
     *
     * @param  BatchShowRequest  $request  The validated request
     * @param  string  $type  Content type (e.g., 'post', 'doc')
     *
     * @throws \Exception
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
