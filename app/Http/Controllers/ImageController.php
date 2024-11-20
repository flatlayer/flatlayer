<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageDimensionException;
use App\Http\Requests\ImageTransformRequest;
use App\Models\Image;
use App\Services\Media\ImageTransformer;
use App\Services\Storage\StorageResolver;
use Illuminate\Http\JsonResponse;

class ImageController extends Controller
{
    public function __construct(
        protected StorageResolver $diskResolver
    ) {}

    public function transform(ImageTransformRequest $request, int $id, string $extension)
    {
        $image = Image::with('entry')->findOrFail($id);
        $entry = $image->entry;

        // Resolve the disk based on entry type
        $disk = $this->diskResolver->resolve(null, $entry->type);
        $service = new ImageTransformer($disk);

        try {
            $transform = $request->validated();
            $transform['fm'] = $extension;

            $transformedImage = $service->transformImage($image->path, $transform);

            return $service->createImageResponse($transformedImage, $extension);
        } catch (ImageDimensionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred while processing the image'], 500);
        }
    }

    public function metadata(int $id): JsonResponse
    {
        $image = Image::with('entry')->findOrFail($id);

        return new JsonResponse([
            'width' => $image->dimensions['width'],
            'height' => $image->dimensions['height'],
            'mime_type' => $image->mime_type,
            'size' => $image->size,
            'filename' => $image->filename,
            'thumbhash' => $image->thumbhash,
        ]);
    }
}
