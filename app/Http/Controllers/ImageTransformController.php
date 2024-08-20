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
}
