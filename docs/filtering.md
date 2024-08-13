# Filter Query Language Documentation

## Overview

The Filter Query Language (FQL) in Flatlayer CMS is a JSON-based query language designed for filtering and ordering data in API requests. It supports field filtering with various operations, tag filtering, full-text search queries, and result ordering. A key feature of FQL is its ability to filter on meta fields, where most of the content-specific data is stored.

## Basic Structure

The filter is a single JSON object where keys represent fields, meta fields, or special operators, and values represent the filtering criteria or ordering instructions.

```json
{
    "field1": <expression>,
    "meta.field2": <expression>,
    "$and": [<expression>, <expression>, ...],
    "$or": [<expression>, <expression>, ...],
    "$search": <search expression>,
    "$tags": <tags expression>,
    "$order": <ordering expression>
}
```

## Field Filters

Field filters allow you to filter on specific fields of your data, including meta fields.

### Standard Fields

Standard fields are those directly on the Entry model, such as `id`, `type`, `title`, `slug`, `content`, `excerpt`, and `published_at`.

### Meta Fields

Meta fields are custom fields stored in the `meta` JSON column. To filter on meta fields, use dot notation:

```json
{
  "meta.author": "John Doe",
  "meta.category": "Technology"
}
```

You can nest as deeply as needed:

```json
{
  "meta.details.location.city": "New York"
}
```

### Equality

To filter for an exact match:

```json
{
  "title": "My Blog Post",
  "meta.category": "Technology"
}
```

### Comparison Operators

For numeric or date fields (including meta fields), you can use comparison operators:

- `$gt`: Greater than
- `$gte`: Greater than or equal to
- `$lt`: Less than
- `$lte`: Less than or equal to
- `$ne`: Not equal to

Example:
```json
{
  "meta.views": { "$gte": 1000 },
  "meta.rating": { "$gt": 4.5 }
}
```

### String Operations

- `$like`: Pattern matching (SQL LIKE)

Example:
```json
{
  "title": { "$like": "%Laravel%" },
  "meta.tags": { "$like": "%php%" }
}
```

### Array Operations

- `$in`: Match any value in an array
- `$notIn`: Match none of the values in an array

Example:
```json
{
  "meta.category": { "$in": ["Technology", "Programming"] },
  "meta.author": { "$notIn": ["John Doe", "Jane Smith"] }
}
```

### Existence Checks

- `$exists`: Check if a field exists or not
- `$notExists`: Check if a field does not exist

Example:
```json
{
  "meta.featured": { "$exists": true },
  "meta.deprecated": { "$notExists": true }
}
```

### Null Checks

- `$null`: Check if a field is null
- `$notNull`: Check if a field is not null

Example:
```json
{
  "meta.reviewDate": { "$notNull": true }
}
```

### Range Queries

- `$between`: Check if a value is between two values (inclusive)
- `$notBetween`: Check if a value is not between two values (exclusive)

Example:
```json
{
  "meta.price": { "$between": [10, 50] },
  "meta.stock": { "$notBetween": [0, 5] }
}
```

### Array Containment

For meta fields that contain arrays:

- `$contains`: Check if an array field contains a specific value
- `$notContains`: Check if an array field does not contain a specific value

Example:
```json
{
  "meta.tags": { "$contains": "Laravel" },
  "meta.excludedTopics": { "$notContains": "Legacy" }
}
```

## Logical Operators

### AND

The `$and` operator performs a logical AND operation on an array of expressions:

```json
{
  "$and": [
    { "meta.status": "published" },
    { "meta.featured": true },
    { "meta.views": { "$gt": 1000 } }
  ]
}
```

### OR

The `$or` operator performs a logical OR operation on an array of expressions:

```json
{
  "$or": [
    { "meta.category": "Technology" },
    { "meta.tags": { "$contains": "Programming" } }
  ]
}
```

## Full-Text Search

The `$search` operator performs an AI-powered vector search on the main content and title of entries. This search is more sophisticated than a simple keyword match, allowing for semantic understanding and relevance ranking.

```json
{
  "$search": "Advanced Laravel techniques"
}
```

**Important Notes on Full-Text Search:**
1. The search is limited to the `content` and `title` fields of entries. It does not search within meta fields or tags.
2. The `$search` operation is applied after all other filters. This means you can narrow down the dataset before applying the search, which can lead to more relevant results and better performance.
3. The search uses AI-powered vector embeddings, allowing for semantic understanding beyond exact keyword matches.

### Combining Search with Other Filters

You can combine the full-text search with other filters to refine your results. For example:

```json
{
  "type": "blog_post",
  "meta.category": "Technology",
  "published_at": { "$gte": "2023-01-01" },
  "$search": "Laravel performance optimization"
}
```

This query would:
1. First filter for blog posts in the Technology category published since January 1, 2023.
2. Then perform a full-text search for "Laravel performance optimization" on the resulting set of entries.

By applying filters before the search, you can:
- Improve search performance by reducing the number of documents that need to be searched.
- Increase the relevance of search results by pre-filtering to a specific subset of entries.
- Combine structured filtering (e.g., by date, category, or other meta fields) with the power of semantic search.

## Filter Application Order

1. All non-search filters are applied first (field filters, meta filters, logical operators, tag filters).
2. The resulting dataset is then searched using the `$search` operator, if present.
3. Finally, the results are ordered according to the `$order` specification. In the case of search, the order is based on relevance unless overridden by the `$order` operator.

This order of operations ensures that the potentially more expensive text search is performed on a pre-filtered dataset, which can lead to better performance and more relevant results.

## Tag Filtering

To filter by tags:

```json
{
  "$tags": ["Laravel", "PHP"]
}
```

This will return entries that have at least one of the specified tags.

## Result Ordering

To specify the order of the results, use the `$order` operator:

```json
{
  "$order": {
    "published_at": "desc",
    "meta.views": "desc"
  }
}
```

You can order by both standard and meta fields.

## Complex Example

Here's a complex example that combines various filters on both standard and meta fields:

```json
{
  "type": "blog_post",
  "meta.category": { "$in": ["Technology", "Programming"] },
  "meta.author": "John Doe",
  "meta.publishedYear": { "$gte": 2023 },
  "meta.tags": { "$contains": "Laravel" },
  "$and": [
    { "meta.rating": { "$gte": 4.0 } },
    { "meta.comments_count": { "$gt": 10 } }
  ],
  "$or": [
    { "meta.featured": true },
    { "meta.views": { "$gt": 5000 } }
  ],
  "$search": "Advanced Laravel techniques",
  "$tags": ["php", "web-development"],
  "$order": {
    "published_at": "desc",
    "meta.views": "desc"
  }
}
```

This query would:
1. Filter for blog posts
2. In the Technology or Programming categories
3. Written by John Doe
4. Published in or after 2023
5. Tagged with "Laravel"
6. With a rating of 4.0 or higher and more than 10 comments
7. Either featured or with more than 5000 views
8. Containing content related to "Advanced Laravel techniques"
9. Tagged with either "php" or "web-development"
10. Ordered by publish date (newest first) and then by view count (highest first)

## Performance Considerations

- Use specific field filters when possible to narrow down the dataset before applying more complex operations.
- Avoid using `$search` on large datasets without other filters, as it can be computationally expensive.
- When querying meta fields, consider creating indexes on frequently queried properties for better performance.
- Complex queries on deeply nested meta fields may impact performance. Consider flattening your meta structure if you need to frequently query deeply nested data.

By leveraging the Filter Query Language effectively, especially with meta fields, you can create powerful, flexible queries to retrieve exactly the data you need from your Flatlayer CMS.
