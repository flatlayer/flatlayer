<?php

namespace App\Query;

use App\Models\Entry;
use App\Models\Image;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EntrySerializer
{
    protected $defaultFields = [
        'id', 'type', 'title', 'slug', 'content', 'excerpt', 'published_at', 'meta', 'tags', 'images'
    ];

    protected $defaultSummaryFields = [
        'id', 'type', 'title', 'slug', 'excerpt', 'published_at', 'tags', 'images'
    ];

    protected $defaultDetailFields = [
        'id', 'type', 'title', 'slug', 'content', 'excerpt', 'published_at', 'meta', 'tags', 'images'
    ];

    /**
     * Convert an Entry to an array with specified or default fields.
     */
    public function toArray(Entry $item, array $fields = []): array
    {
        return $this->convertToArray($item, $fields ?: $this->defaultFields);
    }

    /**
     * Convert an Entry to a summary array with specified or default summary fields.
     */
    public function toSummaryArray(Entry $item, array $fields = []): array
    {
        return $this->convertToArray($item, $fields ?: $this->defaultSummaryFields);
    }

    /**
     * Convert an Entry to a detailed array with specified or default detail fields.
     */
    public function toDetailArray(Entry $item, array $fields = []): array
    {
        return $this->convertToArray($item, $fields ?: $this->defaultDetailFields);
    }

    /**
     * Convert an Entry to an array based on the specified fields.
     */
    protected function convertToArray(Entry $item, array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $value = $this->getFieldValue($item, $field);
                if ($value !== null || (Str::startsWith($field, 'meta.') && Arr::has($item->meta, Str::after($field, 'meta.')))) {
                    Arr::set($result, $field, $value);
                }
            } elseif (is_array($field) && count($field) >= 2) {
                $fieldName = $field[0];
                $options = $field[1];
                $value = $this->getFieldValue($item, $fieldName, $options);
                if ($value !== null || (Str::startsWith($fieldName, 'meta.') && Arr::has($item->meta, Str::after($fieldName, 'meta.')))) {
                    Arr::set($result, $fieldName, $value);
                }
            }
        }

        return $result;
    }

    /**
     * Get the value of a field from an Entry.
     *
     * @param mixed $options Optional casting or formatting options
     */
    protected function getFieldValue(Entry $item, string $field, mixed $options = null): mixed
    {
        if (Str::startsWith($field, 'meta.')) {
            return $this->getMetaValue($item, Str::after($field, 'meta.'), $options);
        }

        if (Str::startsWith($field, 'images.')) {
            return $this->getImage($item, Str::after($field, 'images.'), $options);
        }

        switch ($field) {
            case 'tags':
                return $item->tags->pluck('name')->toArray();
            case 'images':
                return $this->getImages($item, $options);
            case 'meta':
                return $this->getAllMetaValues($item, $options);
            default:
                $value = $item->$field;
                return $options !== null ? $this->castValue($value, $options) : $value;
        }
    }

    /**
     * Get a meta value from an Entry.
     *
     * @param mixed $options Optional casting or formatting options
     */
    protected function getMetaValue(Entry $item, string $key, mixed $options = null): mixed
    {
        $value = Arr::get($item->meta, $key);

        if ($value === null && !Arr::has($item->meta, $key)) {
            return null;
        }

        return $options !== null ? $this->castValue($value, $options) : $value;
    }

    /**
     * Get all meta values from an Entry.
     *
     * @param mixed $options Optional casting or formatting options
     */
    protected function getAllMetaValues(Entry $item, mixed $options = null): mixed
    {
        if (!is_array($options)) {
            return $item->meta;
        }

        $result = [];
        foreach ($options as $key => $opt) {
            $value = $this->getMetaValue($item, $key, $opt);
            if ($value !== null) {
                Arr::set($result, $key, $value);
            }
        }
        return $result;
    }

    /**
     * Cast a value based on the provided options.
     *
     * @param mixed $value The value to cast
     * @param mixed $options The casting options
     */
    protected function castValue(mixed $value, mixed $options = null): mixed
    {
        if ($options === null) {
            return $value;
        }

        if ($options instanceof Closure) {
            return $options($value);
        }

        if (is_array($options)) {
            return $value;
        }

        switch ($options) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'string':
                return (string) $value;
            case 'array':
                return is_array($value) ? $value : explode(',', $value);
            case 'date':
                return $this->castToDate($value);
            case 'datetime':
                return $this->castToDateTime($value);
            default:
                return $value;
        }
    }

    /**
     * Cast a value to a date string.
     */
    protected function castToDate(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }
        if (is_string($value)) {
            return Carbon::parse($value)->toDateString();
        }
        return '';
    }

    /**
     * Cast a value to a datetime string.
     */
    protected function castToDateTime(mixed $value): string
    {
        return $value instanceof Carbon
            ? $value->toDateTimeString()
            : Carbon::parse($value)->toDateTimeString();
    }

    /**
     * Get images from a specific collection of an Entry.
     *
     * @param mixed $options Optional formatting options
     */
    protected function getImage(Entry $item, string $collection, mixed $options = null): array
    {
        $mediaItems = $item->getImages($collection);
        return $mediaItems->map(function ($mediaItem) use ($options) {
            return $this->formatImage($mediaItem, $options);
        })->toArray();
    }

    /**
     * Get all images from an Entry.
     *
     * @param mixed $options Optional formatting options
     */
    /**
     * Get formatted images for an entry.
     *
     * @param Entry $entry The entry to get images for
     * @param mixed $options Formatting options for the images
     * @return array An array of formatted images grouped by collection
     */
    protected function getImages(Entry $entry, mixed $options = null): array
    {
        $formattedImages = [];
        $collections = $entry->images()->get()->groupBy('collection');

        foreach ($collections as $collection => $imagesInCollection) {
            $formattedImages[$collection] = $imagesInCollection->map(function ($image) use ($options) {
                return $this->formatImage($image, $options);
            })->toArray();
        }

        return $formattedImages;
    }

    /**
     * Format an image with the given options.
     *
     * @param Image $image The media item to format
     * @param mixed $options Optional formatting options
     */
    protected function formatImage(Image $image, mixed $options = null): array
    {
        $sizes = $options['sizes'] ?? ['100vw'];
        $attributes = $options['attributes'] ?? [];
        $fluid = $options['fluid'] ?? true;
        $displaySize = $options['display_size'] ?? null;

        $customProperties = is_string($image->custom_properties)
            ? json_decode($image->custom_properties, true)
            : ($image->custom_properties ?? []);

        $meta = array_merge([
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
            'aspect_ratio' => $image->getAspectRatio(),
            'filename' => $image->filename,
        ], $customProperties);

        return [
            'id' => $image->id,
            'url' => $image->getUrl(),
            'html' => $image->getImgTag($sizes, $attributes, $fluid, $displaySize),
            'meta' => $meta,
        ];
    }
}
