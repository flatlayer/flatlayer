<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageRequest;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Http\Response;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class ImageController extends Controller
{
    protected ImageManager $manager;
    protected $optimizer;
    protected $diskName = 'public'; // Make sure this matches the disk name in the test

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
        $this->optimizer = OptimizerChainFactory::create();
    }

    public function transform(ImageRequest $request, $id)
    {
        // Check for a signature if required
        if (config('flatlayer.media.use_signatures') && !$request->hasValidSignature()) {
            abort(401);
        }

        $media = Media::findOrFail($id);

        $cacheKey = $this->generateCacheKey($id, $request->all());
        $cachePath = $this->getCachePath($cacheKey);

        if (Storage::disk($this->diskName)->exists($cachePath)) {
            return $this->serveCachedImage($cachePath);
        }

        $image = $this->manager->read($media->path);

        $width = $request->input('w');
        $height = $request->input('h');

        if ($width && $height) {
            $image->cover($width, $height);
        } elseif ($width) {
            $image->scale(width: $width);
        } elseif ($height) {
            $image->scale(height: $height);
        }

        $quality = (int) $request->input('q', 90);
        $format = $request->input('fm', pathinfo($media->path, PATHINFO_EXTENSION));

        $encoded = match ($format) {
            'jpg', 'jpeg' => $image->toJpeg($quality),
            'png' => $image->toPng(),
            'webp' => $image->toWebp($quality),
            'gif' => $image->toGif(),
            default => $image->encode(),
        };

        // Optimize the image
        $tempFile = tempnam(sys_get_temp_dir(), 'optimized_image');
        file_put_contents($tempFile, $encoded);
        $this->optimizer->optimize($tempFile);
        $optimizedImage = file_get_contents($tempFile);
        unlink($tempFile);

        // Cache the optimized image
        Storage::disk($this->diskName)->put($cachePath, $optimizedImage);

        return $this->serveImage($optimizedImage, $format);
    }

    private function generateCacheKey($id, array $params): string
    {
        ksort($params); // Ensure consistent order of parameters
        return md5($id . serialize($params));
    }

    private function getCachePath(string $cacheKey): string
    {
        return 'cache/images/' . $cacheKey;
    }

    private function serveCachedImage(string $cachePath): Response
    {
        $cachedImage = Storage::disk($this->diskName)->get($cachePath);
        $format = pathinfo($cachePath, PATHINFO_EXTENSION);
        return $this->serveImage($cachedImage, $format);
    }

    private function serveImage(string $imageData, string $format): Response
    {
        $contentType = $this->getContentType($format);
        return new Response($imageData, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($imageData),
            'Cache-Control' => 'public, max-age=31536000',
            'Etag' => md5($imageData),
        ]);
    }

    private function getContentType(string $format): string
    {
        return match ($format) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
}
