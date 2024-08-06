# Filter Query Language Documentation

## Overview

The Filter Query Language (FQL) is a JSON-based query language designed for filtering and ordering data in API requests. It's loosly based on MongoDB's query language. It supports field filtering with various operations, tag filtering, full-text search queries, and result ordering.

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
    "$orderBy": <ordering expression>
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

[The content for tag filtering remains the same as in the original document]

## Result Ordering

To specify the order of the results, use the `$orderBy` operator:

```json
{
  "$orderBy": [
    ["field1", "asc"],
    ["field2", "desc"]
  ]
}
```

The `$orderBy` value is an array of arrays, where each inner array contains two elements:
1. The field name to sort by
2. The sort direction: "asc" for ascending order or "desc" for descending order

You can specify multiple fields for sorting. The results will be sorted by the first field, then by the second field for any results that have the same value for the first field, and so on.

Example:
```json
{
  "status": "active",
  "$orderBy": [
    ["last_name", "asc"],
    ["first_name", "asc"]
  ]
}
```

This filter would find all active users and sort them first by last name in ascending order, then by first name in ascending order for users with the same last name.

## Combining Filters and Ordering

You can combine various filters with ordering:

```json
{
  "status": "active",
  "age": { "$gte": 18 },
  "$search": "developer",
  "$tags": ["technology"],
  "$or": [
    { "country": "USA" },
    { "country": "Canada" }
  ],
  "$orderBy": [
    ["join_date", "desc"],
    ["last_name", "asc"]
  ]
}
```

This filter would:
1. Find active users
2. Filter for users aged 18 or older
3. Filter for users tagged with "technology"
4. Filter for users in either the USA or Canada
5. Perform a full-text search for "developer" on this filtered subset of users
6. Order the results first by join date (most recent first), then by last name (alphabetically) for users who joined on the same date

## Filter Application Order

1. All non-search filters are applied first (field filters, logical operators, tag filters).
2. The resulting dataset is then searched using the `$search` operator, if present.
3. Finally, the results are ordered according to the `$orderBy` specification.

This order of operations ensures that the potentially more expensive text search is performed on a pre-filtered dataset, which can lead to better performance and more relevant results. The ordering is applied last to ensure all filtering and searching is complete before sorting the final result set.
