# Field Selection Documentation

## Overview

The Field Selection feature allows API consumers to specify exactly which fields they want to retrieve and how they want those fields formatted. This powerful functionality enables clients to request only the data they need, in the format they prefer, potentially reducing payload size and improving application performance.

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
2. The options (string for simple casting, or object for complex options)

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

### Complex Options

For fields that require more complex options (like images), provide an object:

```json
[
  "id",
  "title",
  ["featured_image", {
    "sizes": ["100vw"],
    "display_size": [800, 600],
    "attributes": {"class": "featured-img"},
    "fluid": true
  }]
]
```

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

Image fields support several options to control how they're returned:

- `sizes`: An array of size descriptors for responsive images
- `display_size`: An array with two elements [width, height] for the display size
- `attributes`: An object of HTML attributes to add to the image tag
- `fluid`: A boolean indicating if the image should use fluid sizing

Example:
```json
[
  "id",
  "title",
  ["hero_image", {
    "sizes": ["(min-width: 768px) 50vw", "100vw"],
    "display_size": [1200, 800],
    "attributes": {"class": "hero-img", "alt": "Hero image"},
    "fluid": true
  }]
]
```

## Multiple Image Collections

If your content items support multiple image collections, you can specify them using dot notation:

```json
[
  "id",
  "title",
  ["images.hero", {"sizes": ["100vw"], "display_size": [1200, 800]}],
  ["images.thumbnail", {"sizes": ["100px"], "display_size": [100, 100]}]
]
```

## Combining with Filtering

Field selection can be combined with filtering in API requests. Use the `fields` parameter for field selection and the `filter` parameter for filtering:

```
GET /api/content?fields=["id","title",["published_at","date"],["images.featured",{"sizes":["100vw"]}]]&filter={"status":"published","$orderBy":[["published_at","desc"]]}
```

This request would return published content items, ordered by publish date descending, including only the id, title, formatted publish date, and featured image URL.

## Usage in List vs Detail Views

- In list views (when requesting multiple items), you might want to select fewer fields to reduce payload size:

```
GET /api/content?fields=["id","title","excerpt",["images.thumbnail",{"sizes":["100px"]}]]
```

- In detail views (when requesting a single item), you might select more fields:

```
GET /api/content/my-blog-post?fields=["id","title","content",["published_at","date"],["images.featured",{"sizes":["100vw"]}],"meta.author","meta.category"]
```

By using field selection effectively, you can optimize your API requests to retrieve exactly the data you need in the format that best suits your application.
