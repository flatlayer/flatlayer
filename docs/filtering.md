# Filter Query Language Documentation

## Overview

The Filter Query Language (FQL) is a JSON-based query language designed for filtering data in API requests. It supports field filtering with various operations, tag filtering, and full-text search queries.

## Basic Structure

The filter is a single JSON object where keys represent fields or special operators, and values represent the filtering criteria.

```json
{
    "field1": <expression>,
    "field2": <expression>,
    "$and": [<expression>, <expression>, ...],
    "$or": [<expression>, <expression>, ...],
    "$search": <search expression>,
    "$tags": <tags expression>
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

### In Operator

To match any of multiple values:

```json
{
  "status": { "$in": ["active", "pending"] }
}
```

### Exists Operator

To check if a field exists or not:

```json
{
  "email": { "$exists": true }
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

**Important Note**: The `$search` operation is handled differently from other filters. All other filters are applied to the dataset before the search query is executed. This ensures that the search is performed on the already filtered subset of data, potentially improving performance and accuracy of results.

## Tag Filtering

To filter by tags:

```json
{
  "$tags": {
    "type": "category",
    "values": ["technology", "science"]
  }
}
```

Or for default tag type:

```json
{
  "$tags": ["technology", "science"]
}
```

## Combining Filters

You can combine various filters:

```json
{
  "status": "active",
  "age": { "$gte": 18 },
  "$search": "developer",
  "$tags": ["technology"],
  "$or": [
    { "country": "USA" },
    { "country": "Canada" }
  ]
}
```

This filter would first apply all non-search filters:
1. Find active users
2. Filter for users aged 18 or older
3. Filter for users tagged with "technology"
4. Filter for users in either the USA or Canada

Then, it would perform a full-text search for "developer" on this filtered subset of users.

## Filter Application Order

1. All non-search filters are applied first (field filters, logical operators, tag filters).
2. The resulting dataset is then searched using the `$search` operator, if present.

This order of operations ensures that the potentially more expensive text search is performed on a pre-filtered dataset, which can lead to better performance and more relevant results.
