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

    public function toArray(ContentItem $item, ?array $fields = null): array
    {
        $fields = $fields ?? $this->defaultFields;
        $result = [];

        foreach ($fields as $field => $options) {
            if (is_numeric($field)) {
                $field = $options;
                $options = null;
            }

            $value = $this->getFieldValue($item, $field, $options);
            if ($value !== null) {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    protected function getFieldValue(ContentItem $item, string $field, $options = null)
    {
        if (Str::startsWith($field, 'meta.')) {
            return $this->getMetaValue($item, Str::after($field, 'meta.'), $options);
        }

        switch ($field) {
            case 'tags':
                return $item->tags->pluck('name')->toArray();
            case 'images':
                return $this->getImages($item, $options);
            case 'meta':
                return $this->getAllMetaValues($item, $options);
            default:
                return $item->$field;
        }
    }

    protected function getMetaValue(ContentItem $item, string $key, $options = null)
    {
        $value = Arr::get($item->meta, $key);

        if ($value === null) {
            return null;
        }

        return $this->castValue($value, $options);
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
        if ($options === null) {
            return $value;
        }

        $castTo = is_string($options) ? $options : ($options['cast'] ?? null);

        switch ($castTo) {
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
            $images[$collection] = $mediaItems->map(function ($mediaItem) use ($options, $collection) {
                $imageOptions = $options[$collection] ?? [];
                return [
                    'id' => $mediaItem->id,
                    'url' => $mediaItem->getUrl(),
                    'html' => $mediaItem->getImgTag(
                        $imageOptions['sizes'] ?? ['100vw'],
                        $imageOptions['attributes'] ?? [],
                        $imageOptions['fluid'] ?? true
                    ),
                ];
            })->toArray();
        }

        return $images;
    }
}
