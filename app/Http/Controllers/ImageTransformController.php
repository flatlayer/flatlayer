<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageTransformRequest;
use App\Models\Image;
use App\Services\ImageTransformationService;

class ImageTransformController extends Controller
{
    public function __construct(
        protected ImageTransformationService $imageService
    ) {}

    public function transform(ImageTransformRequest $request, $id)
    {
        if (config('flatlayer.images.use_signatures') && !$request->hasValidSignature()) {
            abort(401);
        }

        $media = Image::findOrFail($id);

        $format = $request->input('fm', pathinfo($media->path, PATHINFO_EXTENSION));
        $cacheKey = $this->imageService->generateCacheKey($id, $request->all());
        $cachePath = $this->imageService->getCachePath($cacheKey, $format);

        $cachedImage = $this->imageService->getCachedImage($cachePath);
        if ($cachedImage) {
            return $this->imageService->createImageResponse($cachedImage, $format);
        }

        $transformedImage = $this->imageService->transformImage($media->path, $request->all());
        $this->imageService->cacheImage($cachePath, $transformedImage);

        return $this->imageService->createImageResponse($transformedImage, $format);
    }
}
