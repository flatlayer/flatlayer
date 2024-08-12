<?php

namespace App\Query;

use App\Models\Entry;
use App\Models\Image;
use App\Query\Exceptions\CastException;
use App\Query\Exceptions\InvalidCastException;
use App\Query\Exceptions\QueryException;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EntrySerializer
{
    private readonly array $defaultFields;

    private readonly array $defaultSummaryFields;

    private readonly array $defaultDetailFields;

    public function __construct()
    {
        $this->defaultFields = [
            'id', 'type', 'title', 'slug', 'content', 'excerpt', 'published_at', 'meta', 'tags', 'images',
        ];

        $this->defaultSummaryFields = [
            'id', 'type', 'title', 'slug', 'excerpt', 'published_at', 'tags', 'images',
        ];

        $this->defaultDetailFields = [
            'id', 'type', 'title', 'slug', 'content', 'excerpt', 'published_at', 'meta', 'tags', 'images',
        ];
    }

    /**
     * Convert an Entry to an array with specified or default fields.
     *
     * @throws QueryException
     * @throws InvalidCastException
     */
    public function toArray(Entry $item, array $fields = []): array
    {
        try {
            return $this->convertToArray($item, $fields ?: $this->defaultFields);
        } catch (InvalidCastException|CastException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new QueryException('Error converting Entry to array: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Convert an Entry to a summary array with specified or default summary fields.
     *
     * @throws Exception
     */
    public function toSummaryArray(Entry $item, array $fields = []): array
    {
        return $this->convertToArray($item, $fields ?: $this->defaultSummaryFields);
    }

    /**
     * Convert an Entry to a detailed array with specified or default detail fields.
     *
     * @throws Exception
     */
    public function toDetailArray(Entry $item, array $fields = []): array
    {
        return $this->convertToArray($item, $fields ?: $this->defaultDetailFields);
    }

    /**
     * Convert an Entry to an array based on the specified fields.
     *
     * @throws InvalidCastException
     */
    protected function convertToArray(Entry $item, array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $this->processField($result, $item, $field);
            } elseif (is_array($field) && count($field) >= 2) {
                $this->processField($result, $item, $field[0], $field[1]);
            }
        }

        return $result;
    }

    /**
     * Process a single field and set its value in the result array.
     *
     * @throws InvalidCastException
     */
    protected function processField(array &$result, Entry $item, string $field, mixed $options = null): void
    {
        $value = $this->getFieldValue($item, $field, $options);
        if ($value !== null || (Str::startsWith($field, 'meta.') && Arr::has($item->meta, Str::after($field, 'meta.')))) {
            Arr::set($result, $field, $value);
        }
    }

    /**
     * Get the value of a field from an Entry.
     *
     * @throws InvalidCastException
     */
    protected function getFieldValue(Entry $item, string $field, mixed $options = null): mixed
    {
        return match (true) {
            Str::startsWith($field, 'meta.') => $this->getMetaValue($item, Str::after($field, 'meta.'), $options),
            Str::startsWith($field, 'images.') => $this->getImagesFromCollection($item, Str::after($field, 'images.')),
            $field === 'tags' => $item->tags->pluck('name')->toArray(),
            $field === 'images' => $this->getImages($item),
            $field === 'meta' => $this->getAllMetaValues($item, $options),
            default => $this->getDefaultFieldValue($item, $field, $options),
        };
    }

    /**
     * Get the value for a default field.
     *
     * @throws InvalidCastException
     */
    protected function getDefaultFieldValue(Entry $item, string $field, mixed $options = null): mixed
    {
        $value = $item->$field;

        return $options !== null ? $this->castValue($value, $options) : $value;
    }

    /**
     * Get a meta value from an Entry.
     *
     * @throws InvalidCastException
     */
    protected function getMetaValue(Entry $item, string $key, mixed $options = null): mixed
    {
        $value = Arr::get($item->meta, $key);

        if ($value === null && ! Arr::has($item->meta, $key)) {
            return null;
        }

        return $options !== null ? $this->castValue($value, $options) : $value;
    }

    /**
     * Get all meta values from an Entry.
     *
     * @throws InvalidCastException
     */
    protected function getAllMetaValues(Entry $item, mixed $options = null): mixed
    {
        if (! is_array($options)) {
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
     * @throws InvalidCastException|CastException
     */
    protected function castValue(mixed $value, mixed $options = null): mixed
    {
        if ($options === null || is_array($options)) {
            return $value;
        }

        try {
            return match ($options) {
                'int', 'integer' => (int) $value,
                'float', 'double' => (float) $value,
                'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'string' => (string) $value,
                'array' => is_array($value) ? $value : explode(',', $value),
                'date' => $this->castToDate($value),
                'datetime' => $this->castToDateTime($value),
                default => is_callable($options) ? $options($value) : throw new InvalidCastException("Invalid cast option: $options"),
            };
        } catch (InvalidCastException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new CastException('Error casting value: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Cast a value to a date string.
     *
     * @throws CastException
     */
    protected function castToDate(mixed $value): string
    {
        return $this->castToCarbon($value)->toDateString();
    }

    /**
     * Cast a value to a datetime string.
     *
     * @throws CastException
     */
    protected function castToDateTime(mixed $value): string
    {
        return $this->castToCarbon($value)->toDateTimeString();
    }

    /**
     * Cast a value to a Carbon instance.
     *
     * @throws CastException
     */
    protected function castToCarbon(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (Exception $e) {
                throw new CastException('Unable to parse date/time string: '.$e->getMessage(), 0, $e);
            }
        }
        throw new CastException('Unable to cast value to Carbon instance');
    }

    /**
     * Get images from a specific collection of an Entry.
     */
    protected function getImagesFromCollection(Entry $item, string $collection): array
    {
        return $item->getImages($collection)
            ->map(fn ($image) => $this->formatImage($image))
            ->values()
            ->toArray();
    }

    /**
     * Get formatted images for an entry.
     */
    protected function getImages(Entry $entry): array
    {
        return $entry->images()
            ->get()
            ->groupBy('collection')
            ->map(fn ($imagesInCollection) => $imagesInCollection
                ->map(fn ($image) => $this->formatImage($image))
                ->values()
                ->toArray()
            )
            ->toArray();
    }

    /**
     * Format an image with basic information.
     */
    protected function formatImage(Image $image): array
    {
        return $image->toArray();
    }
}
