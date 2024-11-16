# Pagination in Flatlayer CMS

## Overview

Pagination in Flatlayer CMS enables efficient retrieval of large datasets in manageable chunks. The system uses a cursor-based pagination approach combined with traditional limit-offset mechanisms to ensure consistent performance and reliability when dealing with large content collections.

## Basic Pagination

### Request Parameters

When making a request to any list endpoint, you can include these pagination parameters:

- `page`: Page number to retrieve (default: 1)
- `per_page`: Number of items per page (default: 15, maximum: 100)

```http
GET /entry/posts?page=2&per_page=20
```

### Response Structure

Paginated responses include both the requested data and comprehensive pagination metadata:

```json
{
    "data": [
        {
            "id": 1,
            "title": "First Post",
            "type": "post",
            "published_at": "2024-01-01T12:00:00Z",
            "meta": {
                "author": "John Doe"
            }
        },
        // ... more items
    ],
    "pagination": {
        "current_page": 2,
        "total_pages": 5,
        "per_page": 20
    }
}
```

## Implementation

### Backend Usage

The `EntryQueryBuilder` class handles pagination through the `SimplePaginator`:

```php
$query = Entry::query();
$filter = new EntryFilter($query, $request->getFilter());
$filteredResult = $filter->apply();

$paginatedResult = $filteredResult->simplePaginate(
    $perPage,
    ['*'],
    'page',
    $page,
    $arrayConverter,
    $fields,
    $filteredResult->isSearch()
);
```

### Frontend SDK Usage

Using the Flatlayer SDK, you can implement pagination in several ways:

#### Basic List Retrieval

```javascript
const flatlayer = new Flatlayer('https://api.yourflatlayerinstance.com');

// Get a specific page of entries
flatlayer.getEntryList('post', {
    page: 2,
    perPage: 20,
    fields: ['title', 'excerpt', 'author']
})
    .then(response => {
        console.log('Posts:', response.data);
        console.log('Current page:', response.pagination.current_page);
        console.log('Total pages:', response.pagination.total_pages);
    })
    .catch(error => console.error('Error fetching posts:', error));
```

#### Retrieving All Pages

For cases where you need to retrieve all items across multiple pages:

```javascript
async function getAllPosts() {
    const flatlayer = new Flatlayer('https://api.yourflatlayerinstance.com');
    let page = 1;
    let allPosts = [];
    let hasMorePages = true;

    while (hasMorePages) {
        try {
            const response = await flatlayer.getEntryList('post', { 
                page, 
                perPage: 100,
                fields: ['title', 'excerpt', 'published_at']
            });
            
            allPosts = allPosts.concat(response.data);
            hasMorePages = response.pagination.current_page < response.pagination.total_pages;
            page++;
        } catch (error) {
            console.error('Error fetching page:', error);
            break;
        }
    }

    return allPosts;
}
```

#### Combining with Filters

Pagination can be combined with filtering for more precise data retrieval:

```javascript
const filter = {
    status: 'published',
    'meta.category': 'technology',
    published_at: { 
        $gte: '2024-01-01' 
    },
    $order: {
        published_at: 'desc'
    }
};

flatlayer.getEntryList('post', {
    page: 1,
    perPage: 20,
    filter,
    fields: ['title', 'excerpt', 'author', 'published_at']
})
    .then(response => {
        console.log('Filtered posts:', response.data);
    })
    .catch(error => console.error('Error:', error));
```

#### Search with Pagination

When using the search functionality:

```javascript
flatlayer.search('JavaScript', 'post', {
    page: 1,
    perPage: 20,
    fields: ['title', 'excerpt'],
    filter: {
        'meta.category': 'programming'
    }
})
    .then(results => {
        console.log('Search results:', results.data);
        console.log('Pagination info:', results.pagination);
    })
    .catch(error => console.error('Search error:', error));
```

## Performance Optimization

### Backend Considerations

1. **Index Optimization**
    - Ensure proper indexes exist on frequently filtered fields
    - Use composite indexes for common filter combinations
    - Consider partial indexes for specific query patterns

2. **Query Optimization**
    - The `EntryFilter` class efficiently applies filters before pagination
    - Vector searches are optimized for paginated results
    - JSON field queries use appropriate database-specific optimizations

### Frontend Best Practices

1. **Request Size Management**
   ```javascript
   // Prefer smaller page sizes for faster initial loads
   const pageSize = 20; // Balance between payload size and request frequency
   ```

2. **Infinite Scroll Implementation**
   ```javascript
   function loadNextPage(currentPage) {
       return flatlayer.getEntryList('post', {
           page: currentPage + 1,
           perPage: 20,
           fields: ['title', 'excerpt'] // Request only needed fields
       });
   }
   ```

3. **Caching Strategy**
   ```javascript
   const cachedPages = new Map();
   
   async function getPageWithCache(pageNumber) {
       const cacheKey = `posts-page-${pageNumber}`;
       
       if (cachedPages.has(cacheKey)) {
           return cachedPages.get(cacheKey);
       }
       
       const response = await flatlayer.getEntryList('post', {
           page: pageNumber,
           perPage: 20
       });
       
       cachedPages.set(cacheKey, response.data);
       return response.data;
   }
   ```

## Error Handling

The system includes comprehensive error handling for pagination-related issues:

```javascript
flatlayer.getEntryList('post', {
    page: 999999, // Page number beyond available pages
    perPage: 20
})
    .catch(error => {
        if (error instanceof FlatlayerError) {
            if (error.status === 404) {
                console.error('Page not found');
            } else {
                console.error(`API Error (${error.status}):`, error.message);
            }
        } else {
            console.error('Network or other error:', error);
        }
    });
```

## Best Practices

1. **Optimize Initial Load**
    - Start with a smaller `per_page` value for faster initial page loads
    - Increase the page size for subsequent loads if needed

2. **Implement Progressive Loading**
    - Use infinite scroll or "Load More" buttons for better UX
    - Pre-fetch next page for smoother transitions

3. **Handle Edge Cases**
    - Implement proper error handling for out-of-range page numbers
    - Provide clear feedback when no more results are available

4. **Combine with Other Features**
    - Use filtering to reduce the total result set before pagination
    - Implement sorting to help users find content without deep pagination
    - Leverage search functionality for large datasets

5. **Monitor Performance**
    - Track pagination-related metrics
    - Watch for deep pagination patterns that might indicate UX issues
    - Consider implementing alternative navigation patterns for large datasets

By following these guidelines and leveraging the pagination features of Flatlayer CMS effectively, you can build responsive and efficient applications that handle large datasets smoothly while providing an excellent user experience.
