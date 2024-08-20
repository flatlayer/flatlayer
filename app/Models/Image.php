<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
     * Get the file extension of the image.
     *
     * @return string The file extension of the image (e.g., 'jpg', 'png', etc.).
     */
    public function getExtension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    /**
     * Converts the Image model instance to an array representation.
     *
     * This method returns an associative array containing various properties of the Image model.
     *
     * @return array An array representation of the Image model with the following keys:
     *  - id: The unique identifier of the image.
     *  - extension: The file extension of the image (e.g., 'jpg', 'png', etc.).
     *  - filename: The original filename of the image.
     *  - width: The width of the image in pixels.
     *  - height: The height of the image in pixels.
     *  - thumbhash: The thumbhash of the image (if available).
     *  - meta: Additional custom properties associated with the image (if available).
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'extension' => $this->getExtension(),
            'filename' => $this->filename,
            'width' => $this->dimensions['width'] ?? null,
            'height' => $this->dimensions['height'] ?? null,
            'thumbhash' => $this->thumbhash ?? null,
            'meta' => $this->custom_properties ?? [],
        ];
    }
}
