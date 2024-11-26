# Content Filtering API

## Overview

The Filtering API in Flatlayer CMS provides a flexible query language for filtering content entries. Using a JSON-based syntax, it supports complex queries including nested conditions, meta field filtering, tag filtering, and full-text search.

## Query Structure

The filter parameter accepts a JSON object with various operators and conditions:

```json
{
    "field": "value",
    "field2": { "$operator": "value" },
    "$or": [...],
    "$and": [...],
    "$tags": [...],
    "$search": "query string"
}
```

## Basic Filtering

### Simple Equality
```http
GET /entries/post/list?filter={"type":"post"}
```

### Multiple Conditions
```http
GET /entries/post/list?filter={"type":"post","status":"published"}
```

## Comparison Operators

The following operators are supported:

### Numeric Comparisons
```http
GET /entries/post/list?filter={
    "meta.view_count": {
        "$gt": 1000,
        "$lte": 5000
    }
}
```

Available operators:
- `$gt`: Greater than
- `$gte`: Greater than or equal
- `$lt`: Less than
- `$lte`: Less than or equal
- `$ne`: Not equal

### String Operations
```http
GET /entries/post/list?filter={
    "title": {
        "$startsWith": "Getting",
        "$endsWith": "Guide"
    }
}
```

Available operators:
- `$startsWith`: Begins with
- `$endsWith`: Ends with
- `$contains`: Contains substring
- `$like`: SQL LIKE pattern
- `$notStartsWith`: Does not begin with
- `$notEndsWith`: Does not end with

### Array Operations
```http
GET /entries/post/list?filter={
    "meta.categories": {
        "$in": ["tech", "programming"]
    }
}
```

Available operators:
- `$in`: Value in array
- `$notIn`: Value not in array
- `$contains`: Array contains value
- `$notContains`: Array does not contain value

### Null Checks
```http
GET /entries/post/list?filter={
    "meta.review_date": {
        "$exists": false
    }
}
```

Available operators:
- `$exists`: Field exists (not null)
- `$notExists`: Field does not exist (is null)

## Logical Operators

### AND Operations
```http
GET /entries/post/list?filter={
    "$and": [
        {
            "type": "post"
        },
        {
            "published_at": {
                "$lte": "2024-01-01"
            }
        }
    ]
}
```

### OR Operations
```http
GET /entries/post/list?filter={
    "$or": [
        {
            "meta.category": "tech"
        },
        {
            "meta.category": "programming"
        }
    ]
}
```

### Combined Operations
```http
GET /entries/post/list?filter={
    "$and": [
        {
            "type": "post"
        },
        {
            "$or": [
                {
                    "meta.category": "tech"
                },
                {
                    "meta.difficulty": "beginner"
                }
            ]
        }
    ]
}
```

## Meta Field Filtering

### Simple Meta Field
```http
GET /entries/post/list?filter={
    "meta.author": "John Doe"
}
```

### Nested Meta Fields
```http
GET /entries/post/list?filter={
    "meta.seo.keywords": {
        "$contains": "javascript"
    }
}
```

### Multiple Meta Conditions
```http
GET /entries/post/list?filter={
    "meta.difficulty": "advanced",
    "meta.estimated_time": {
        "$lte": 60
    }
}
```

## Tag Filtering

### Filter by Tags
```http
GET /entries/post/list?filter={
    "$tags": ["programming", "javascript"]
}
```

### Combined with Other Filters
```http
GET /entries/post/list?filter={
    "$tags": ["programming"],
    "meta.difficulty": "beginner"
}
```

## Path-Based Filtering

### Hierarchical Queries
```http
GET /entries/doc/list?filter={
    "slug": {
        "$startsWith": "docs/getting-started"
    }
}
```

### Relationship Filters
```http
GET /entries/doc/list?filter={
    "$hierarchy": {
        "descendants": "docs/tutorials",
        "siblings": "docs/getting-started/installation"
    }
}
```

Available path operators:
- `$isChildOf`: Direct child of path
- `$isDescendantOf`: Any descendant of path
- `$isSiblingOf`: Sibling of path
- `$hasParent`: Has specified parent

## Full-Text Search

### Basic Search
```http
GET /entries/post/list?filter={
    "$search": "getting started with javascript"
}
```

### Combined with Filters
```http
GET /entries/post/list?filter={
    "$search": "javascript tutorial",
    "meta.difficulty": "beginner",
    "$tags": ["programming"]
}
```

## Sorting

### Basic Sorting
```http
GET /entries/post/list?filter={
    "$order": {
        "published_at": "desc"
    }
}
```

### Multiple Fields
```http
GET /entries/post/list?filter={
    "$order": {
        "meta.category": "asc",
        "published_at": "desc"
    }
}
```

## Error Responses

### Invalid Filter Syntax
```http
Status: 400 Bad Request

{
    "error": "Invalid filter syntax"
}
```

### Invalid Operator
```http
Status: 400 Bad Request

{
    "error": "Invalid operator: $invalidOp"
}
```

### Invalid Field Reference
```http
Status: 400 Bad Request

{
    "error": "Invalid field reference: nonexistent.field"
}
```

## Performance Considerations

1. **Index Usage**
    - Meta field queries utilize database-specific JSON indexing
    - Path-based queries use specialized path indexes
    - Full-text search uses vector indexes where available

2. **Query Optimization**
    - Use specific field filters instead of full-text search when possible
    - Prefer direct field matches over pattern matching
    - Use tag filtering instead of meta field arrays when appropriate

3. **Result Limits**
    - Default limit: 100 items
    - Maximum limit: 1000 items
    - Use pagination for large result sets

## Combined Example

A complex query combining multiple filter types:

```http
GET /entries/post/list?filter={
    "$and": [
        {
            "type": "tutorial",
            "published_at": {
                "$lte": "2024-01-01"
            }
        },
        {
            "$or": [
                {
                    "meta.difficulty": "beginner"
                },
                {
                    "meta.category": "getting-started"
                }
            ]
        }
    ],
    "$tags": ["javascript", "programming"],
    "$search": "async await promises",
    "$order": {
        "published_at": "desc"
    }
}
```

This documentation covers the complete filtering capabilities of the Flatlayer CMS API. Each filter type is designed to work efficiently with the underlying database structure while providing powerful query capabilities.
