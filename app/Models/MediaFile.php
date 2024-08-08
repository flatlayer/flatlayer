<?php

namespace App\Models;

use App\Services\ResponsiveImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_type',
        'model_id',
        'collection',
        'filename',
        'path',
        'mime_type',
        'size',
        'dimensions',
        'thumbhash',
    ];

    protected $casts = [
        'size' => 'integer',
        'dimensions' => 'array',
    ];

    public function model()
    {
        return $this->morphTo();
    }

    public function getWidth(): ?int
    {
        return $this->dimensions['width'] ?? null;
    }

    public function getHeight(): ?int
    {
        return $this->dimensions['height'] ?? null;
    }

    public function getAspectRatio(): ?float
    {
        if ($this->getWidth() && $this->getHeight()) {
            return $this->getWidth() / $this->getHeight();
        }
        return null;
    }

    public function getImgTag(array $sizes, array $attributes = [], bool $isFluid = true, ?array $displaySize = null): string
    {
        $service = app(ResponsiveImageService::class);

        $defaultAttributes = [
            'alt' => $attributes['alt'] ?? '',
            'data-thumbhash' => $this->thumbhash,
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return $service->generateImgTag($this, $sizes, $attributes, $isFluid, $displaySize);
    }

    public function getUrl(array $transforms = []): string
    {
        // Prioritize the 'fm' (format) transform if it exists
        $extension = $transforms['fm'] ?? pathinfo($this->path, PATHINFO_EXTENSION);

        $route = route('media.transform', [
            'id' => $this->id,
            'extension' => !empty($extension) ? $extension : 'jpg'
        ]);

        if (!empty($transforms)) {
            $queryString = http_build_query($transforms);
            $route .= '?' . $queryString;
        }

        if (config('flatlayer.media.use_signatures', true)) {
            return URL::signedRoute('media.transform', array_merge(
                ['id' => $this->id, 'extension' => $extension],
                $transforms
            ));
        }

        return $route;
    }
}
