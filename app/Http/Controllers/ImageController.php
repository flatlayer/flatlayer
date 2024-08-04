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

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
        $this->optimizer = OptimizerChainFactory::create();
    }

    public function transform(ImageRequest $request, $id)
    {
        $media = Media::findOrFail($id);
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

        $quality = $request->input('q', 90);
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

        $contentType = $this->getContentType($format);

        return new Response($optimizedImage, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($optimizedImage),
            'Cache-Control' => 'public, max-age=31536000',
            'Etag' => md5($optimizedImage),
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
