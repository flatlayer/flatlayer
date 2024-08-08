<?php

namespace App\Filters;

use App\Models\ContentItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ContentItemArrayConverter
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

    public function toArray(ContentItem $item, array $fields = []): array
    {
        return $this->convertToArray($item, $fields ?: $this->defaultFields);
    }

    public function toSummaryArray(ContentItem $item, array $fields = []): array
    {
        return $this->convertToArray($item, $fields ?: $this->defaultSummaryFields);
    }

    public function toDetailArray(ContentItem $item, array $fields = []): array
    {
        return $this->convertToArray($item, $fields ?: $this->defaultDetailFields);
    }

    protected function convertToArray(ContentItem $item, array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $value = $this->getFieldValue($item, $field);
                if ($value !== null) {
                    Arr::set($result, $field, $value);
                }
            } elseif (is_array($field) && count($field) >= 2) {
                $fieldName = $field[0];
                $options = $field[1];
                $value = $this->getFieldValue($item, $fieldName, $options);
                if ($value !== null) {
                    Arr::set($result, $fieldName, $value);
                }
            }
        }

        return $result;
    }

    protected function getFieldValue(ContentItem $item, string $field, $options = null)
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
                return $options ? $this->castValue($value, $options) : $value;
        }
    }

    protected function getMetaValue(ContentItem $item, string $key, $options = null)
    {
        $value = Arr::get($item->meta, $key);
        return $value !== null ? $this->castValue($value, $options) : null;
    }

    protected function getAllMetaValues(ContentItem $item, $options = null)
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

    protected function castValue($value, $options = null)
    {
        if ($options === null || is_array($options)) {
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

    protected function castToDate($value)
    {
        return $value instanceof Carbon
            ? $value->toDateString()
            : Carbon::parse($value)->toDateString();
    }

    protected function castToDateTime($value)
    {
        return $value instanceof Carbon
            ? $value->toDateTimeString()
            : Carbon::parse($value)->toDateTimeString();
    }

    protected function getImages(ContentItem $item, $options = null): array
    {
        $images = [];
        $collections = $item->getMedia()->groupBy('collection');

        foreach ($collections as $collection => $mediaItems) {
            $images[$collection] = $mediaItems->map(function ($mediaItem) use ($options) {
                return $this->formatImage($mediaItem, $options);
            })->toArray();
        }

        return $images;
    }

    protected function getImage(ContentItem $item, string $collection, $options = null)
    {
        $mediaItem = $item->getFirstMedia($collection);
        return $mediaItem ? $this->formatImage($mediaItem, $options) : null;
    }

    protected function formatImage($mediaItem, $options = null): array
    {
        $sizes = $options['sizes'] ?? ['100vw'];
        $attributes = $options['attributes'] ?? [];
        $fluid = $options['fluid'] ?? true;
        $displaySize = $options['display_size'] ?? null;

        return [
            'id' => $mediaItem->id,
            'url' => $mediaItem->getUrl(),
            'html' => $mediaItem->getImgTag($sizes, $attributes, $fluid, $displaySize),
            'meta' => $mediaItem->custom_properties,
        ];
    }
}
