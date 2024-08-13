# Flatlayer CMS

Flatlayer CMS is a powerful, API-first content management system built on Laravel and Git. It combines the simplicity of flat-file content storage with advanced features like AI-powered vector search and a flexible query language. This makes Flatlayer ideal for managing and searching large documentation sets, content repositories, or any project requiring efficient content organization and retrieval via API.

## Key Features

- **Git-based Content Synchronization**: Seamlessly sync your content from Git repositories, enabling version control and collaborative editing.
- **AI-powered Vector Search**: Utilize Jina.ai's advanced embedding and reranking models for intelligent content discovery.
- **Advanced Query Language**: Powerful filtering capabilities for precise content retrieval, including complex nested queries and JSON field filtering.
- **Field Selection**: Specify exactly which fields to retrieve, reducing payload size and improving performance.
- **Image Processing and Caching**: Automatic image optimization and efficient caching system.
- **Webhook Support**: Enable automatic updates triggered by repository changes.
- **Flexible Configuration**: Easily customizable through environment variables, including content sync configurations.
- **Markdown Support**: Native handling of Markdown files with front matter.
- **Tagging System**: Organize and filter content using a flexible tagging system.

## Requirements

- PHP 8.2+
- Composer
- Laravel 11.x
- PostgreSQL database (recommended for vector search capabilities)
- Git
- Imagick PHP extension (for image processing)

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/flatlayer-cms.git
   cd flatlayer-cms
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Configure your environment:
   ```
   cp .env.example .env
   ```
   Edit `.env` with your specific settings.

4. Generate an application key:
   ```
   php artisan key:generate
   ```

5. Run database migrations:
   ```
   php artisan migrate
   ```

6. Set up the storage link:
   ```
   php artisan storage:link
   ```

## Configuration

Flatlayer CMS is primarily configured through environment variables. Key configuration options include:

### Database

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flatlayer
DB_USERNAME=
DB_PASSWORD=
```

### Jina.ai Configuration

```
JINA_API_KEY=your_jina_api_key
JINA_RERANK_MODEL=jina-reranker-v2-base-multilingual
JINA_EMBED_MODEL=jina-embeddings-v2-base-en
```

### GitHub Webhook

```
GITHUB_WEBHOOK_SECRET=your_webhook_secret
```

### Content Sync Configuration

Configure your content sources using environment variables. Each content type (e.g., posts, pages) has its own set of configuration options:

```
FLATLAYER_SYNC_[TYPE]_PATH="/path/to/content"
FLATLAYER_SYNC_[TYPE]_PATTERN="*.md"
FLATLAYER_SYNC_[TYPE]_WEBHOOK="http://example.com/webhook/[type]"
FLATLAYER_SYNC_[TYPE]_PULL=true
```

Replace `[TYPE]` with your content type (e.g., POSTS, PAGES). Available settings for each type are:

- `PATH`: The directory path where the content is located
- `PATTERN`: The glob pattern for finding content files (default is usually "*.md")
- `WEBHOOK`: The webhook URL for this content type (optional)
- `PULL`: Whether to pull latest changes from Git before syncing (true/false)

Example configuration for posts and pages:

```
FLATLAYER_SYNC_POSTS_PATH="/path/to/posts"
FLATLAYER_SYNC_POSTS_PATTERN="*.md"
FLATLAYER_SYNC_POSTS_WEBHOOK="http://example.com/webhook/posts"
FLATLAYER_SYNC_POSTS_PULL=true

FLATLAYER_SYNC_PAGES_PATH="/path/to/pages"
FLATLAYER_SYNC_PAGES_PATTERN="**/*.md"
FLATLAYER_SYNC_PAGES_WEBHOOK="http://example.com/webhook/pages"
FLATLAYER_SYNC_PAGES_PULL=false
```

## Content Synchronization

### Manual Sync

Manually sync content:

```
php artisan flatlayer:entry-sync --type=posts
```

### Webhook Sync

Set up a GitHub webhook to trigger automatic syncs on repository changes. The webhook URL should be:

```
POST https://your-domain.com/webhook/{type}
```

Where `{type}` corresponds to your sync configuration (e.g., `posts`, `pages`).

## API Usage

### Content Retrieval

#### List Entries
```
GET /api/entry/{type}
```

Parameters:
- `filter`: JSON string for filtering (optional)
- `fields`: JSON array of fields to retrieve (optional)
- `page`: Page number for pagination (optional)
- `per_page`: Items per page (optional)

Example:
```
GET /api/entry/posts?filter={"status":"published"}&fields=["id","title","excerpt"]&page=1&per_page=10
```

#### Get Single Entry
```
GET /api/entry/{type}/{slug}
```

Parameters:
- `fields`: JSON array of fields to retrieve (optional)

Example:
```
GET /api/entry/posts/my-blog-post?fields=["id","title","content","published_at"]
```

### Filtering

Use the advanced filtering capabilities in the `filter` parameter:

```json
{
  "status": "published",
  "meta.category": { "$in": ["technology", "programming"] },
  "$search": "Laravel",
  "$tags": ["web-development"],
  "$order": {
    "published_at": "desc"
  }
}
```

### Field Selection

Specify fields to retrieve using the `fields` parameter:

```json
["id", "title", ["published_at", "date"], "meta.author", "tags"]
```

### Image Transformation

Transform images via API:

```
GET /image/{id}.{extension}?w=800&h=600&q=80
```

Parameters:
- `w`: Width (optional)
- `h`: Height (optional)
- `q`: Quality (1-100, optional)
- `fm`: Format (jpg, png, webp, optional)

## Development

### Coding Standards

We use Laravel Pint for code styling. Run before committing:

```
composer format
```

### Static Analysis

We use Larastan for static analysis:

```
composer larastan
```

## Testing

Run the test suite:

```
composer test
```

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a new branch: `git checkout -b feature/your-feature-name`
3. Make your changes and commit them: `git commit -m 'Add some feature'`
4. Push to the branch: `git push origin feature/your-feature-name`
5. Submit a pull request

Please ensure your code adheres to our coding standards and is well-documented.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For questions, issues, or feature requests, please use the GitHub issue tracker.

---

Thank you for using Flatlayer CMS! We hope it serves your content management and API needs effectively.
