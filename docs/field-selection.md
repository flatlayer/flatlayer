# Field Selection Documentation

## Overview

The Field Selection feature in Flatlayer CMS allows API consumers to specify exactly which fields they want to retrieve from the content entries. This functionality enables clients to request only the data they need, potentially reducing payload size and improving application performance.

## Basic Structure

Field selection is specified using a JSON array. Each element in the array can be either a string (representing a simple field name) or an array (representing a field with additional options).

```json
[
    "field1",
    "field2",
    ["field3", <options>],
    ["nested.field", <options>]
]
```

## Simple Field Selection

To select fields without any special formatting or options, simply include the field names as strings in the array:

```json
["id", "title", "published_at", "author"]
```

This will return only the specified fields for each item in the result set.

## Field Selection with Options

For fields that require special formatting or additional options, use an array with two elements:

1. The field name (string)
2. The options (string for simple casting)

### Simple Casting

For basic type casting, provide the desired type as a string:

```json
[
    "id",
    ["published_at", "date"],
    ["views_count", "integer"],
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

## Nested Fields

To select nested fields (particularly useful for `meta` fields), use dot notation:

```json
[
    "id",
    "title",
    "meta.description",
    ["meta.view_count", "integer"]
]
```

## Images

There are two ways to select images:

1. Select all images:
   ```json
   ["id", "title", "images"]
   ```
   This will return an object containing all image collections, with each image including its full data set.

2. Select images from a specific collection:
   ```json
   ["id", "title", "images.featured"]
   ```
   This will return an array of images from the specified collection (in this case, "featured").

Example response for all images:
```json
{
  "id": 1,
  "title": "Example Post",
  "images": {
    "featured": [
      {
        "id": 1,
        "filename": "featured-image.jpg",
        "extension": "jpg",
        "width": 1200,
        "height": 800,
        "thumbhash": "abcdef1234567890",
        "meta": {
          "alt": "Featured image description",
          "caption": "Image caption"
        }
      }
    ],
    "gallery": [
      {
        "id": 2,
        "filename": "gallery-image-1.jpg",
        "extension": "jpg",
        "width": 800,
        "height": 600,
        "thumbhash": "0987654321fedcba",
        "meta": {
          "alt": "Gallery image 1 description"
        }
      },
      {
        "id": 3,
        "filename": "gallery-image-2.png",
        "extension": "png",
        "width": 1000,
        "height": 750,
        "thumbhash": "1a2b3c4d5e6f7g8h",
        "meta": {
          "alt": "Gallery image 2 description"
        }
      }
    ]
  }
}
```

Example response for a specific image collection:
```json
{
  "id": 1,
  "title": "Example Post",
  "images.featured": [
    {
      "id": 1,
      "filename": "featured-image.jpg",
      "extension": "jpg",
      "width": 1200,
      "height": 800,
      "thumbhash": "abcdef1234567890",
      "meta": {
        "alt": "Featured image description",
        "caption": "Image caption"
      }
    }
  ]
}
```

## Tags

Tags are automatically included as an array of tag names when the "tags" field is selected:

```json
[
  "id",
  "title",
  "tags"
]
```

## Combining with Filtering

Field selection can be combined with filtering in API requests. Use the `fields` parameter for field selection and the `filter` parameter for filtering:

```
GET /api/content?fields=["id","title",["published_at","date"],"images.featured"]&filter={"published_at":{"$gte":"2023-01-01"},"$orderBy":{"published_at":"desc"}}
```

This request would return content items published since January 1, 2023, ordered by publish date descending, including only the id, title, formatted publish date, and featured images.

## Usage in List vs Detail Views

- In list views (when requesting multiple items), you might want to select fewer fields to reduce payload size:

```
GET /api/content?fields=["id","title","excerpt","images.thumbnail"]
```

- In detail views (when requesting a single item), you might select more fields:

```
GET /api/content/my-blog-post?fields=["id","title","content",["published_at","date"],"images","meta.author","meta.category","tags"]
```

By using field selection effectively, you can optimize your API requests to retrieve exactly the data you need.

## Default Fields

If no fields are specified, the system will use default field sets:

- For list views: id, type, title, slug, excerpt, published_at, tags, and images
- For detail views: id, type, title, slug, content, excerpt, published_at, meta, tags, and images

You can override these defaults by specifying your own field selection.
