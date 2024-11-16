<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowRequest;
use App\Models\Entry;
use App\Query\EntrySerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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
        // Clean the slug
        $slug = trim($slug, '/');

        // Handle potential index redirects
        if (!Str::endsWith($slug, '/index')) {
            // Check for index file at this level
            $indexEntry = Entry::where('type', $type)
                ->where('slug', $slug.'/index')
                ->first();

            if ($indexEntry) {
                return response()->json([
                    'redirect' => true,
                    'location' => "/entry/{$type}/{$slug}/index",
                ], 307);
            }

            // Check for index file in parent directory if this path doesn't exist
            $contentItem = Entry::where('type', $type)
                ->where('slug', $slug)
                ->first();

            if (!$contentItem) {
                $parentSlug = Str::beforeLast($slug, '/');
                $parentIndexEntry = Entry::where('type', $type)
                    ->where('slug', $parentSlug.'/index')
                    ->first();

                if ($parentIndexEntry) {
                    return response()->json([
                        'redirect' => true,
                        'location' => "/entry/{$type}/{$parentSlug}/index",
                    ], 307);
                }
            }
        }

        // Get the entry
        $contentItem = Entry::where('type', $type)
            ->where('slug', $slug)
            ->first();

        if (!$contentItem) {
            return response()->json(['error' => 'No item found for the specified type and slug'], 404);
        }

        $fields = $request->getFields();
        $result = $this->arrayConverter->toDetailArray($contentItem, $fields);

        // Add hierarchical navigation data if requested
        if ($request->includes('navigation')) {
            $result['navigation'] = [
                'ancestors' => $contentItem->ancestors()
                    ->map(fn($entry) => [
                        'title' => $entry->title,
                        'slug' => $entry->slug,
                        'is_index' => $entry->is_index,
                    ]),
                'siblings' => $contentItem->siblings()
                    ->map(fn($entry) => [
                        'title' => $entry->title,
                        'slug' => $entry->slug,
                        'is_index' => $entry->is_index,
                    ]),
                'children' => $contentItem->children()
                    ->map(fn($entry) => [
                        'title' => $entry->title,
                        'slug' => $entry->slug,
                        'is_index' => $entry->is_index,
                    ]),
            ];

            if ($contentItem->is_index) {
                // For index files, include parent navigation
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
    public function batch(ShowRequest $request, string $type): JsonResponse
    {
        $slugs = $request->getSlugs();

        if (empty($slugs)) {
            return response()->json(['error' => 'No valid slugs provided'], 400);
        }

        // Prepare index redirects
        $redirectSlugs = [];
        $processedSlugs = [];

        foreach ($slugs as $slug) {
            $normalizedSlug = trim($slug, '/');
            if (!Str::endsWith($normalizedSlug, '/index')) {
                $indexSlug = $normalizedSlug.'/index';
                $redirectSlugs[$normalizedSlug] = $indexSlug;
            }
            $processedSlugs[] = $normalizedSlug;
        }

        // Add potential index slugs to the query
        $query = Entry::where('type', $type)
            ->where(function ($query) use ($processedSlugs, $redirectSlugs) {
                $query->whereIn('slug', $processedSlugs)
                    ->orWhereIn('slug', array_values($redirectSlugs));
            });

        if ($query->doesntExist()) {
            return response()->json(['error' => 'No items found for the specified type and slugs'], 404);
        }

        $fields = $request->getFields();
        $slugOrder = collect($slugs)->flip();

        $items = $query->get()
            ->map(function (Entry $item) use ($redirectSlugs, $type) {
                // Check if this is an index file that should trigger a redirect
                $originalSlug = array_search($item->slug, $redirectSlugs);
                if ($originalSlug !== false) {
                    return [
                        'redirect' => true,
                        'from' => $originalSlug,
                        'to' => $item->slug,
                        'location' => "/entry/{$type}/{$item->slug}",
                        'is_index' => true,
                    ];
                }

                $result = $this->arrayConverter->toDetailArray($item);
                $result['is_index'] = $item->is_index;
                return $result;
            })
            ->sortBy(fn ($item) =>
                $slugOrder[$item['redirect'] ?? false ? $item['from'] : $item['slug']] ?? PHP_INT_MAX
            )
            ->values();

        return response()->json(['data' => $items]);
    }
}
