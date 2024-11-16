# Field Selection Documentation

## Overview

Field Selection in Flatlayer CMS enables precise control over which fields are returned in API responses. Implemented through the `EntrySerializer` class, this feature allows clients to minimize payload size and optimize data transfer by specifying exactly which fields they need.

## Basic Structure

Field selection uses a JSON array where each element can be:
- A string (simple field name)
- An array (field name with casting options)

```json
[
    "field1",
    "field2",
    ["field3", "cast_type"],
    ["nested.field", "cast_type"]
]
```

## Simple Field Selection

For basic field selection without casting:

```json
["id", "title", "published_at", "author"]
```

The `EntrySerializer` will include only these fields in the response.

## Field Casting

The system supports type casting through the `castValue` method:

```json
[
    "id",
    ["published_at", "date"],
    ["view_count", "integer"],
    ["is_featured", "boolean"]
]
```

Supported cast types:
- `"integer"` or `"int"`
- `"float"` or `"double"`
- `"boolean"` or `"bool"`
- `"string"`
- `"date"`
- `"datetime"`
- `"array"`

Example implementation:
```php
protected function castValue(mixed $value, mixed $options = null): mixed
{
    if ($options === null || is_array($options)) {
        return $value;
    }

    return match ($options) {
        'int', 'integer' => (int) $value,
        'float', 'double' => (float) $value,
        'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        'string' => (string) $value,
        'array' => is_array($value) ? $value : explode(',', $value),
        'date' => $this->castToDate($value),
        'datetime' => $this->castToDateTime($value),
        default => is_callable($options) ? $options($value) : 
            throw new InvalidCastException("Invalid cast option: $options")
    };
}
```

## Meta Fields

Access nested meta fields using dot notation:

```json
[
    "id",
    "title",
    "meta.author",
    ["meta.view_count", "integer"],
    "meta.seo.description"
]
```

The `EntrySerializer` handles nested meta field access:
```php
protected function getMetaValue(Entry $item, string $key, mixed $options = null): mixed
{
    $value = Arr::get($item->meta, $key);

    if ($value === null && !Arr::has($item->meta, $key)) {
        return null;
    }

    return $options !== null ? $this->castValue($value, $options) : $value;
}
```

## Images

### Full Image Selection
```json
["id", "title", "images"]
```

Returns all image collections with complete image data:
```json
{
    "id": 1,
    "title": "Example Post",
    "images": {
        "featured": [{
            "id": 1,
            "filename": "featured.jpg",
            "extension": "jpg",
            "width": 1200,
            "height": 800,
            "thumbhash": "abcdef1234567890",
            "meta": {
                "alt": "Featured image",
                "caption": "Main featured image"
            }
        }],
        "gallery": [
            // ... gallery images
        ]
    }
}
```

### Collection-Specific Selection
```json
["id", "title", "images.featured"]
```

Returns images from a specific collection:
```json
{
    "id": 1,
    "title": "Example Post",
    "images": {
        "featured": [{
            "id": 1,
            "filename": "featured.jpg",
            "extension": "jpg",
            "width": 1200,
            "height": 800,
            "thumbhash": "abcdef1234567890",
            "meta": {
                "alt": "Featured image"
            }
        }]
    }
}
```

## Tags

Tag selection automatically converts to tag names:

```json
["id", "title", "tags"]
```

Implementation:
```php
protected function getFieldValue(Entry $item, string $field, mixed $options = null): mixed
{
    return match (true) {
        $field === 'tags' => $item->tags->pluck('name')->toArray(),
        $field === 'images' => $this->getImages($item),
        $field === 'meta' => $this->getAllMetaValues($item, $options),
        default => $this->getDefaultFieldValue($item, $field, $options)
    };
}
```

## Default Field Sets

The `EntrySerializer` defines default field sets:

```php
private readonly array $defaultFields = [
    'id', 'type', 'title', 'slug', 'content', 'excerpt', 
    'published_at', 'meta', 'tags', 'images'
];

private readonly array $defaultSummaryFields = [
    'id', 'type', 'title', 'slug', 'excerpt', 
    'published_at', 'tags', 'images'
];

private readonly array $defaultDetailFields = [
    'id', 'type', 'title', 'slug', 'content', 
    'excerpt', 'published_at', 'meta', 'tags', 'images'
];
```

## API Usage

### List View
```http
GET /entry/post?fields=["id","title","excerpt","images.thumbnail"]
```

### Detail View
```http
GET /entry/post/my-post?fields=["id","title","content",["published_at","date"],"images","meta","tags"]
```

### With Filtering
```http
GET /entry/post?fields=["id","title",["published_at","date"]]&filter={"status":"published"}
```

## Error Handling

The system includes comprehensive error handling:

```php
try {
    $result = $this->convertToArray($item, $fields);
} catch (InvalidCastException|CastException $e) {
    throw $e;
} catch (Exception $e) {
    throw new QueryException(
        'Error converting Entry to array: '.$e->getMessage(),
        0,
        $e
    );
}
```

Specific exceptions:
- `InvalidCastException`: Invalid cast type specified
- `CastException`: Error during value casting
- `QueryException`: General conversion errors

## Performance Considerations

1. **Field Selection**
    - Select only required fields
    - Use summary fields for list views
    - Use detail fields for single-item views

2. **Image Handling**
    - Select specific image collections when possible
    - Use thumbnail collections for list views
    - Request full image data only when needed

3. **Meta Fields**
    - Select specific meta fields rather than entire meta object
    - Use nested field selection for deep meta structures
    - Consider flattening frequently accessed meta fields

4. **Caching**
    - The serializer includes internal caching for expensive operations
    - Use appropriate cache strategies for frequently accessed field combinations

By using field selection effectively, you can optimize your API responses for both performance and bandwidth efficiency while maintaining clean, type-safe data handling.
