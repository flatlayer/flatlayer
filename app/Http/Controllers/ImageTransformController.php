<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageDimensionException;
use App\Http\Requests\ImageTransformRequest;
use App\Models\Image;
use App\Services\ImageTransformationService;
use Illuminate\Http\JsonResponse;

class ImageTransformController extends Controller
{
    public function __construct(
        protected ImageTransformationService $imageService
    ) {}

    public function transform(ImageTransformRequest $request, int $id, string $extension)
    {
        $media = Image::findOrFail($id);

        try {
            $transform = $request->validated();
            $transform['fm'] = $extension;

            $transformedImage = $this->imageService->transformImage($media->path, $transform);

            return $this->imageService->createImageResponse($transformedImage, $extension);
        } catch (ImageDimensionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred while processing the image'], 500);
        }
    }

    public function metadata(int $id): JsonResponse
    {
        $image = Image::findOrFail($id);

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
