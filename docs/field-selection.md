# Field Selection API

## Overview

The Field Selection API enables precise control over which fields are returned in API responses. By specifying exact fields needed, clients can minimize response payload size and optimize data transfer.

## Field Specification

### Basic Syntax

Field selection is specified using a JSON array in the `fields` parameter:

```http
GET /entries/post/list?fields=["id","title","slug"]
```

Response:
```json
{
    "data": [
        {
            "id": 1,
            "title": "Example Post",
            "slug": "example-post"
        }
    ]
}
```

### Field Types

Each element in the fields array can be:
1. A simple string for basic fields
2. An array containing field name and cast type
3. A dot-notation string for nested fields

## Available Fields

### Standard Fields
- `id`: Entry ID
- `type`: Content type
- `title`: Entry title
- `slug`: URL slug
- `content`: Main content
- `excerpt`: Short excerpt
- `published_at`: Publication date
- `meta`: Metadata object
- `tags`: Array of tags
- `images`: Image collections

### Nested Meta Fields

Access nested meta fields using dot notation:

```http
GET /entries/post/list?fields=["meta.author","meta.category"]
```

Response:
```json
{
    "data": [
        {
            "meta": {
                "author": "John Doe",
                "category": "Technology"
            }
        }
    ]
}
```

### Image Collections

Select all image collections:
```http
GET /entries/post/list?fields=["images"]
```

Response:
```json
{
    "data": [
        {
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
                }],
                "gallery": [
                    // ... gallery images
                ]
            }
        }
    ]
}
```

Select specific collection:
```http
GET /entries/post/list?fields=["images.featured"]
```

Response:
```json
{
    "data": [
        {
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
    ]
}
```

## Field Casting

Use array syntax to specify field casting:

```http
GET /entries/post/list?fields=[
    ["published_at", "date"],
    ["view_count", "integer"],
    ["is_featured", "boolean"]
]
```

### Available Cast Types
- `integer` or `int`
- `float` or `double`
- `boolean` or `bool`
- `string`
- `date`
- `datetime`
- `array`

### Cast Type Examples

Date casting:
```http
GET /entries/post/list?fields=[["published_at", "date"]]
```
Response:
```json
{
    "data": [
        {
            "published_at": "2024-01-01"
        }
    ]
}
```

Numeric casting:
```http
GET /entries/post/list?fields=[["meta.view_count", "integer"]]
```
Response:
```json
{
    "data": [
        {
            "meta": {
                "view_count": 1234
            }
        }
    ]
}
```

## Default Field Sets

If no fields are specified, the system uses predefined sets:

### List View Defaults
```json
[
    "id",
    "type",
    "title", 
    "slug",
    "excerpt",
    "published_at",
    "tags",
    "images"
]
```

### Detail View Defaults
```json
[
    "id",
    "type",
    "title",
    "slug",
    "content",
    "excerpt",
    "published_at",
    "meta",
    "tags",
    "images"
]
```

## Error Responses

### Invalid Field Name
```http
Status: 400 Bad Request
```
```json
{
    "error": "Invalid field name: invalid_field"
}
```

### Invalid Cast Type
```http
Status: 400 Bad Request
```
```json
{
    "error": "Invalid cast type: invalid_type"
}
```

### Invalid Field Selection Syntax
```http
Status: 400 Bad Request
```
```json
{
    "error": "Invalid field selection format"
}
```

## Complex Examples

### Combined Fields with Casting
```http
GET /entries/post/list?fields=[
    "id",
    "title",
    ["published_at", "date"],
    "meta.author",
    ["meta.view_count", "integer"],
    "images.featured",
    "tags"
]
```

### With Filtering and Pagination
```http
GET /entries/post/list?fields=[
    "title",
    ["published_at", "date"],
    "meta.category"
]&filter={
    "meta.category": "technology"
}&page=1&per_page=20
```

## Performance Considerations

1. **Response Size**
    - Select only needed fields
    - Use specific image collections instead of all images
    - Consider using excerpts instead of full content in lists

2. **Query Optimization**
    - Group related fields together
    - Use appropriate cast types
    - Minimize nested field depth

## Best Practices

1. **Field Selection**
    - Use minimal fields for list views
    - Request full data only for detail views
    - Group related fields together

2. **Image Handling**
    - Request specific image collections when possible
    - Use thumbnails for list views
    - Request full image data only when needed

3. **Meta Fields**
    - Request specific meta fields rather than entire meta object
    - Use nested field notation for deep meta structures
    - Consider field casting for proper data types

By following these guidelines and using field selection appropriately, you can optimize your API responses for both performance and data efficiency.
