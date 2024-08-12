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
        if (config('flatlayer.images.use_signatures') && ! $request->hasValidSignature()) {
            abort(401);
        }

        $media = Image::findOrFail($id);

        $format = $request->input('fm', $extension);
        $cacheKey = $this->imageService->generateCacheKey($id, $request->validated());
        $cachePath = $this->imageService->getCachePath($cacheKey, $format);

        $cachedImage = $this->imageService->getCachedImage($cachePath);
        if ($cachedImage) {
            return $this->imageService->createImageResponse($cachedImage, $format);
        }

        try {
            $transformedImage = $this->imageService->transformImage($media->path, $request->validated());
            $this->imageService->cacheImage($cachePath, $transformedImage);

            return $this->imageService->createImageResponse($transformedImage, $format);
        } catch (ImageDimensionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred while processing the image'], 500);
        }
    }
}
