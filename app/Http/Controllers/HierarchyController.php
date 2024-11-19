<?php

namespace App\Http\Controllers;

use App\Http\Requests\HierarchyRequest;
use App\Services\HierarchyService;
use Illuminate\Http\JsonResponse;

class HierarchyController extends Controller
{
    public function __construct(
        protected HierarchyService $hierarchyService
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
            $basePath = dirname($path);
            $root = $basePath === '.' ? '' : $basePath;

            $hierarchy = $this->hierarchyService->buildHierarchy(
                type: $type,
                root: $root,
                options: $request->getOptions()
            );

            $node = $this->hierarchyService->findNode($hierarchy, $path);
            if (! $node) {
                return response()->json(['error' => 'Node not found'], 404);
            }

            $ancestry = $this->hierarchyService->getAncestry($hierarchy, $path);

            return response()->json([
                'data' => $node,
                'meta' => [
                    'ancestry' => $ancestry,
                    'depth' => count($ancestry),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to find node'], 500);
        }
    }
}
