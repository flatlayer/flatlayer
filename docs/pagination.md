# Pagination in Flatlayer CMS

## Overview

Flatlayer CMS provides a flexible and efficient pagination system for retrieving large datasets. The system uses a simple paginator approach that provides consistent performance while maintaining compatibility with both SQL and vector-based search results.

## Endpoints Supporting Pagination

All list endpoints support pagination by default:

```http
GET /entries/{type}/list
GET /entries/{type}/batch
GET /entries/{type}/hierarchy
```

### Request Parameters

The following query parameters control pagination:

- `page`: The page number to retrieve (default: 1)
- `per_page`: Number of items per page (default: 15, max: 100)

Example requests:
```http
GET /entries/post/list?page=2&per_page=20
GET /entries/post/list?page=1&per_page=50&filter={"published_at":{"$lte":"2024-01-01"}}
```

### Response Format

All paginated responses follow a consistent format:

```json
{
    "data": [
        {
            "id": 1,
            "type": "post",
            "title": "Example Post",
            "slug": "example-post",
            "excerpt": "Post excerpt...",
            "published_at": "2024-01-01T00:00:00Z",
            "meta": {
                "author": "John Doe"
            }
        }
        // ... additional items
    ],
    "pagination": {
        "current_page": 2,
        "total_pages": 5,
        "per_page": 20
    }
}
```

For search results, each item in the `data` array includes an additional `relevance` score:

```json
{
    "data": [
        {
            "id": 1,
            "title": "Example Post",
            // ... other fields
            "relevance": 0.89
        }
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 3,
        "per_page": 20
    }
}
```

## Error Responses

### Invalid Page Number
```http
Status: 400 Bad Request
```
```json
{
    "error": "Page number must be a positive integer"
}
```

### Page Out of Range
```http
Status: 404 Not Found
```
```json
{
    "error": "The requested page exceeds available pages"
}
```

### Invalid Per Page Value
```http
Status: 400 Bad Request
```
```json
{
    "error": "Items per page must be between 1 and 100"
}
```

## Combining with Other Features

### Field Selection

You can combine pagination with field selection to control the response size:

```http
GET /entries/post/list?page=1&per_page=20&fields=["title","slug","excerpt"]
```

Response:
```json
{
    "data": [
        {
            "title": "Example Post",
            "slug": "example-post",
            "excerpt": "Post excerpt..."
        }
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "per_page": 20
    }
}
```

### Filtering

Pagination works seamlessly with the filtering system:

```http
GET /entries/post/list?page=1&per_page=20&filter={"meta.category":"technology","published_at":{"$gte":"2024-01-01"}}
```

### Search Results

When using the search endpoint, results include relevance scores and maintain consistent pagination:

```http
GET /entries/post/list?page=1&per_page=20&filter={"$search":"example query"}
```

## Implementation Details

The pagination system is implemented using the `SimplePaginator` class, which handles both regular queries and search results. It automatically detects whether the results come from a database query or a search operation and adjusts the pagination accordingly.

Key features of the implementation:

1. **Consistent Response Format**: All paginated responses follow the same structure, making them predictable and easy to work with.

2. **Automatic Type Detection**: The system automatically handles both database queries and search results appropriately.

3. **Field Selection Support**: Pagination works seamlessly with field selection for optimizing response size.

4. **Search Integration**: Full support for vector search results with relevance scores.

## Performance Considerations

1. **Request Size Management**
    - Default page size is set to 15 items
    - Maximum page size is limited to 100 items
    - Use field selection to reduce response payload size

2. **Database Optimization**
    - Indexes are automatically used for sorting and filtering
    - Vector search operations are optimized for pagination
    - JSON field queries use database-specific optimizations

3. **Resource Usage**
    - Deep pagination (high page numbers) should be avoided
    - Consider using filters to reduce the total result set
    - Use field selection to minimize response size

## Best Practices

1. **Parameter Validation**
    - Always provide valid page numbers
    - Stay within the per_page limits (1-100)
    - Use reasonable page sizes for your use case

2. **Resource Optimization**
    - Use field selection to request only needed fields
    - Apply appropriate filters to reduce result sets
    - Avoid deep pagination when possible

3. **Error Handling**
    - Always check response status codes
    - Handle pagination errors appropriately
    - Respect the pagination metadata in responses

By following these guidelines and using the pagination system appropriately, you can efficiently handle large datasets while maintaining good API performance and response times.
