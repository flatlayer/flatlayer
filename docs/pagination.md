# Pagination in Flatlayer CMS

## Overview

Pagination in Flatlayer CMS allows you to retrieve large sets of data in manageable chunks. This feature is crucial for optimizing API performance and improving user experience when dealing with extensive content collections.

## Basic Pagination

List endpoints in Flatlayer CMS use pagination to return results. The API returns a limited number of items per request, along with metadata that allows you to navigate through the entire result set.

### Request Parameters

When making a request to a list endpoint, you can include the following query parameters to control pagination:

- `page`: The page number you want to retrieve (default is 1)
- `per_page`: The number of items to return per page (default is 15, maximum is 100)

Example request:
```
GET /api/entry/posts?page=2&per_page=20
```

This request would retrieve the second page of results, with 20 items per page.

### Response Structure

A paginated response includes both the data for the current page and pagination metadata. Here's an example structure:

```json
{
    "data": [
        {
            "id": 1,
            "title": "First Post",
            ...
        },
        {
            "id": 2,
            "title": "Second Post",
            ...
        },
        ...
    ],
    "pagination": {
        "current_page": 2,
        "total_pages": 5,
        "per_page": 20
    }
}
```

- `data`: An array containing the items for the current page
- `pagination`: Metadata about the pagination
    - `current_page`: The current page number
    - `total_pages`: The total number of pages available
    - `per_page`: The number of items per page

## Navigating Through Pages

To navigate through the pages of results:

1. Start with page 1 (`page=1`)
2. Use the `total_pages` value from the response to determine how many pages are available
3. Increment the `page` parameter to move to the next page
4. Continue until you've reached the last page or have retrieved all the data you need

## Combining Pagination with Filtering

Pagination can be combined with the Filter Query Language to paginate filtered results. For example:

```
GET /api/entry/posts?page=1&per_page=15&filter={"meta.category":"Technology"}
```

This request would return the first page of posts in the Technology category, with 15 items per page.

## Performance Considerations

1. **Limit `per_page`**: While you can request up to 100 items per page, be mindful of the payload size. Larger payloads can increase response times and consume more bandwidth.

2. **Use Filtering**: When possible, use filtering to reduce the total number of results before pagination. This can improve overall performance, especially for large datasets.

3. **Avoid Deep Pagination**: Accessing very high page numbers (e.g., page 1000 of a large dataset) can be inefficient. Consider using filtering or sorting strategies to help users find content without relying on deep pagination.

## Best Practices

1. Always use pagination when retrieving lists of items to ensure consistent performance.
2. Use the `total_pages` value to display accurate pagination controls in your UI.
3. Combine pagination with filtering and sorting to create efficient, user-friendly interfaces for browsing large datasets.
4. Consider implementing caching strategies for frequently accessed pages to improve performance.
5. Use appropriate error handling in your application to deal with cases where a requested page number exceeds the available pages.

By effectively using pagination in Flatlayer CMS, you can create responsive and efficient applications that handle large amounts of data smoothly.
