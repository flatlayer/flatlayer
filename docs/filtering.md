# Filter Query Language Documentation

## Overview

The Filter Query Language (FQL) in Flatlayer CMS is a JSON-based query language designed for filtering and ordering data in API requests. It supports field filtering with various operations, tag filtering, full-text search queries, and result ordering.

## Basic Structure

The filter is a single JSON object where keys represent fields or special operators, and values represent the filtering criteria or ordering instructions.

```json
{
    "field1": <expression>,
    "field2": <expression>,
    "$and": [<expression>, <expression>, ...],
    "$or": [<expression>, <expression>, ...],
    "$search": <search expression>,
    "$tags": <tags expression>,
    "$order": <ordering expression>
}
```

## Field Filters

Field filters allow you to filter on specific fields of your data.

### Equality

To filter for an exact match:

```json
{
  "field": "value"
}
```

### Comparison Operators

For numeric or date fields, you can use comparison operators:

- `$gt`: Greater than
- `$gte`: Greater than or equal to
- `$lt`: Less than
- `$lte`: Less than or equal to
- `$ne`: Not equal to

Example:
```json
{
  "age": { "$gte": 18, "$lte": 65 }
}
```

### String Operations

- `$like`: Pattern matching (SQL LIKE)

Example:
```json
{
  "title": { "$like": "%Laravel%" }
}
```

### Array Operations

- `$in`: Match any value in an array
- `$notIn`: Match none of the values in an array

Example:
```json
{
  "status": { "$in": ["active", "pending"] },
  "category": { "$notIn": ["archived", "deleted"] }
}
```

### Existence Checks

- `$exists`: Check if a field exists or not
- `$notExists`: Check if a field does not exist

Example:
```json
{
  "email": { "$exists": true },
  "deletedAt": { "$notExists": true }
}
```

### Null Checks

- `$null`: Check if a field is null
- `$notNull`: Check if a field is not null

Example:
```json
{
  "lastLoginDate": { "$notNull": true }
}
```

### Range Queries

- `$between`: Check if a value is between two values (inclusive)
- `$notBetween`: Check if a value is not between two values (exclusive)

Example:
```json
{
  "price": { "$between": [10, 50] },
  "quantity": { "$notBetween": [0, 5] }
}
```

## JSON Field Queries

For JSON fields (like `meta`), you can use dot notation to query nested properties:

```json
{
  "meta.author": "John Doe",
  "meta.views": { "$gt": 1000 }
}
```

## Logical Operators

### AND

The `$and` operator performs a logical AND operation on an array of expressions:

```json
{
  "$and": [
    { "status": "active" },
    { "age": { "$gte": 18 } }
  ]
}
```

### OR

The `$or` operator performs a logical OR operation on an array of expressions:

```json
{
  "$or": [
    { "status": "active" },
    { "status": "pending" }
  ]
}
```

## Full-Text Search

To perform a full-text search across searchable fields:

```json
{
  "$search": "search terms"
}
```

**Note**: The `$search` operation is applied after other filters and uses AI-powered vector search for more relevant results.

## Tag Filtering

To filter by tags:

```json
{
  "$tags": ["tag1", "tag2"]
}
```

This will return entries that have at least one of the specified tags.

## Result Ordering

To specify the order of the results, use the `$order` operator:

```json
{
  "$order": {
    "field1": "asc",
    "field2": "desc"
  }
}
```

You can specify multiple fields for sorting. The results will be sorted by the first field, then by subsequent fields for any results that have the same value for the previous fields.

## Combining Filters and Ordering

You can combine various filters with ordering:

```json
{
  "status": "published",
  "meta.category": { "$in": ["technology", "programming"] },
  "$search": "Laravel",
  "$tags": ["web-development"],
  "$or": [
    { "author": "John Doe" },
    { "author": "Jane Smith" }
  ],
  "$order": {
    "published_at": "desc",
    "title": "asc"
  }
}
```

## Filter Application Order

1. All non-search filters are applied first (field filters, logical operators, tag filters).
2. The resulting dataset is then searched using the `$search` operator, if present.
3. Finally, the results are ordered according to the `$order` specification.

This order of operations ensures that the potentially more expensive text search is performed on a pre-filtered dataset, which can lead to better performance and more relevant results.

## Database Support

The Filter Query Language supports both PostgreSQL and SQLite databases, with JSON field queries optimized for each database type.

## Performance Considerations

- Use specific field filters when possible to narrow down the dataset before applying more complex operations.
- Avoid using `$search` on large datasets without other filters, as it can be computationally expensive.
- When querying JSON fields, consider creating indexes on frequently queried properties for better performance.

By using the Filter Query Language effectively, you can create powerful, flexible queries to retrieve exactly the data you need from your Flatlayer CMS.
