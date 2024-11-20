# Search API

## Overview

Flatlayer's search system combines traditional filtering with AI-powered vector search using OpenAI embeddings. The system automatically generates and stores embeddings for all content entries, enabling semantic search capabilities while maintaining efficient query performance.

## Search Endpoint

```http
GET /entry/{type}?filter={"$search":"query"}
```

### Parameters

- `type`: Content type to search within
- `filter`: JSON object containing search query
- `fields`: Optional array of fields to return
- `per_page`: Results per page (default: 15, max: 100)
- `page`: Page number for pagination

### Basic Search Query

```http
GET /entry/doc?filter={"$search":"getting started with javascript"}
```

Response:
```json
{
    "data": [
        {
            "id": 1,
            "title": "JavaScript Introduction",
            "excerpt": "Learn the basics of JavaScript programming",
            "relevance": 0.89
        }
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "per_page": 15
    }
}
```

### Combined Search and Filters

Search can be combined with other filters:

```http
GET /entry/doc?filter={
    "$search": "async programming",
    "meta.difficulty": "beginner",
    "$tags": ["javascript"],
    "$order": {
        "published_at": "desc"
    }
}
```

## Vector Search Implementation

### Text Embeddings

The system uses OpenAI's text embeddings to convert text content into vector representations:

- Model: `text-embedding-3-small`
- Vector size: 1536 dimensions
- Content processed: title, excerpt, and main content
- Updates: Embeddings are automatically generated/updated when content changes

### Search Process

1. Query text is converted to an embedding vector using OpenAI
2. Vector similarity search is performed using:
    - PostgreSQL: Native vector similarity using `pgvector`
    - SQLite: Cosine similarity fallback implementation

### Content Preparation

Each entry's searchable text is constructed from:

```php
"# {title}\n\n{excerpt}\n\n{content}"
```

MDX components are stripped from content before embedding to ensure quality results.

## Database Support

### PostgreSQL Implementation

When using PostgreSQL with the `pgvector` extension:

```sql
SELECT *, (1 - (embedding <=> '[vector]')) as similarity 
FROM entries 
ORDER BY similarity DESC
```

The `<=>` operator computes cosine distance, converted to similarity score.

### SQLite Implementation

For SQLite or other databases, a fallback implementation using the `math-php` library computes cosine similarity:

```php
$similarity = 1 - Distance::cosine($queryVector, $documentVector);
```

## OpenAI Integration

### Configuration

Required environment variables:
```env
OPENAI_API_KEY=your-api-key
OPENAI_ORGANIZATION=your-org-id  # Optional
OPENAI_SEARCH_EMBEDDING_MODEL=text-embedding-3-small
```

### Embedding Generation

Embeddings are automatically generated:
- On content creation
- When content is updated
- When title or excerpt changes

The system uses the OpenAI API to generate embeddings:

```php
$response = OpenAI::embeddings()->create([
    'model' => config('flatlayer.search.openai.embedding'),
    'input' => $text
]);

$embedding = $response->embeddings[0]->embedding;
```

## Relevance Scoring

Search results include a relevance score between 0 and 1:

- 1.0: Perfect match
- >0.8: Very relevant
- >0.6: Moderately relevant
- <0.5: Less relevant

Results are automatically sorted by relevance score unless overridden by explicit ordering.

## Response Format

Search results maintain the standard entry response format with an additional `relevance` field:

```json
{
    "data": [
        {
            "id": 1,
            "type": "doc",
            "title": "Example Document",
            "slug": "example-doc",
            "excerpt": "Document excerpt...",
            "relevance": 0.92,
            "meta": {
                "author": "John Doe"
            }
        }
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "per_page": 15
    }
}
```

## Field Selection

The standard field selection system works with search results:

```http
GET /entry/doc?filter={"$search":"javascript"}&fields=["title","excerpt","meta.author"]
```

Response:
```json
{
    "data": [
        {
            "title": "JavaScript Guide",
            "excerpt": "Complete guide to JavaScript",
            "meta": {
                "author": "John Doe"
            },
            "relevance": 0.88
        }
    ]
}
```

## Error Handling

### Rate Limiting
```http
Status: 429 Too Many Requests

{
    "error": "OpenAI API rate limit exceeded"
}
```

### API Errors
```http
Status: 500 Internal Server Error

{
    "error": "Error generating embeddings"
}
```

## Performance Considerations

1. **Embedding Generation**
    - Occurs asynchronously for content updates
    - Cached in database to avoid regeneration
    - Batch processed during bulk imports

2. **Search Execution**
    - Vector search uses database indexes
    - Results are paginated by default
    - Combined filters use appropriate indexes

3. **Result Caching**
    - Search results are not cached by default
    - Consider caching at the application level if needed
    - Vector similarity calculations are optimized

## Best Practices

1. **Query Construction**
    - Use specific search terms
    - Combine with filters for better results
    - Consider using field selection to reduce payload size

2. **Result Handling**
    - Check relevance scores for quality
    - Use pagination for large result sets
    - Sort by additional criteria if needed

3. **Error Handling**
    - Handle OpenAI API errors gracefully
    - Implement appropriate retry logic
    - Monitor API usage and rate limits

This search system provides powerful semantic search capabilities while maintaining simple API integration. By combining vector search with traditional filtering, it enables both precise and fuzzy content discovery.
