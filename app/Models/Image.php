<?php

namespace App\Models;

use App\Services\ResponsiveImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

/**
 * Class Image
 *
 * Represents an image associated with an entry.
 *
 * @property int $id
 * @property int $entry_id
 * @property string $collection
 * @property string $filename
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property array $dimensions
 * @property array $custom_properties
 * @property string $thumbhash
 */
class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_id',
        'collection',
        'filename',
        'path',
        'mime_type',
        'size',
        'dimensions',
        'custom_properties',
        'thumbhash',
    ];

    protected $casts = [
        'size' => 'integer',
        'dimensions' => 'array',
        'custom_properties' => 'array',
    ];

    /**
     * Get the parent model (polymorphic).
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the width of the image.
     */
    public function getWidth(): ?int
    {
        return $this->dimensions['width'] ?? null;
    }

    /**
     * Get the height of the image.
     */
    public function getHeight(): ?int
    {
        return $this->dimensions['height'] ?? null;
    }

    /**
     * Get the aspect ratio of the image.
     */
    public function getAspectRatio(): ?float
    {
        if ($this->getWidth() && $this->getHeight()) {
            return $this->getWidth() / $this->getHeight();
        }
        return null;
    }

    /**
     * Generate an HTML img tag for the image.
     */
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

    /**
     * Get the URL for the image with optional transformations.
     */
    public function getUrl(array $transforms = []): string
    {
        // Prioritize the 'fm' (format) transform if it exists
        $extension = $transforms['fm'] ?? pathinfo($this->path, PATHINFO_EXTENSION);

        $route = route('image.transform', [
            'id' => $this->id,
            'extension' => !empty($extension) ? $extension : 'jpg'
        ]);

        if (!empty($transforms)) {
            $queryString = http_build_query($transforms);
            $route .= '?' . $queryString;
        }

        if (config('flatlayer.images.use_signatures', true)) {
            return URL::signedRoute('image.transform', array_merge(
                ['id' => $this->id, 'extension' => $extension],
                $transforms
            ));
        }

        return $route;
    }
}
