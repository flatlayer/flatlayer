<?php

namespace App\Http\Controllers;

use App\Http\Requests\HierarchyRequest;
use App\Services\Content\ContentHierarchy;
use Illuminate\Http\JsonResponse;

class HierarchyController extends Controller
{
    public function __construct(
        protected ContentHierarchy $hierarchyService
    ) {}

    public function index(HierarchyRequest $request, string $type): JsonResponse
    {
        try {
            $hierarchy = $this->hierarchyService->buildHierarchy(
                type: $type,
                root: $request->getRoot(),
                options: $request->getOptions()
            );

            return response()->json([
                'data' => $hierarchy,
                'meta' => [
                    'type' => $type,
                    'root' => $request->getRoot(),
                    'depth' => $request->input('depth'),
                    'total_nodes' => count($this->hierarchyService->flattenHierarchy($hierarchy)),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate hierarchy'], 500);
        }
    }

    public function find(HierarchyRequest $request, string $type, string $path): JsonResponse
    {
        try {
            $hierarchy = $this->hierarchyService->buildHierarchy(
                type: $type,
                root: $path,  // Use the full path as the root
                options: $request->getOptions()
            );

            // If no hierarchy was found for this root, it means the path doesn't exist
            if (empty($hierarchy)) {
                return response()->json(['error' => 'Node not found'], 404);
            }

            return response()->json([
                'data' => $hierarchy,
                'meta' => [
                    'type' => $type,
                    'root' => $path,
                    'depth' => $request->input('depth'),
                    'total_nodes' => count($this->hierarchyService->flattenHierarchy($hierarchy)),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to find node'], 500);
        }
    }
}
