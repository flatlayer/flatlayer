<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaTransformRequest;
use App\Models\MediaFile;
use App\Services\ImageTransformationService;

class MediaTransformController extends Controller
{
    protected $imageService;

    public function __construct(ImageTransformationService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function transform(MediaTransformRequest $request, $id)
    {
        if (config('flatlayer.media.use_signatures') && !$request->hasValidSignature()) {
            abort(401);
        }

        $media = MediaFile::findOrFail($id);

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
