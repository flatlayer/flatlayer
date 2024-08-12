# Flatlayer CMS

Flatlayer CMS is a powerful, Git-based content management system built on Laravel. It combines the simplicity of flat-file content storage with advanced features like AI-powered vector search and a flexible query language. This makes Flatlayer ideal for managing and searching large documentation sets, content repositories, or any project requiring efficient content organization and retrieval.

## Key Features

- **Git-based Content Synchronization**: Seamlessly sync your content from Git repositories, enabling version control and collaborative editing.
- **AI-powered Vector Search**: Utilize Jina.ai's advanced embedding and reranking models for intelligent content discovery.
- **Advanced Query Language**: Powerful filtering capabilities for precise content retrieval.
- **Image Processing and Caching**: Automatic image optimization and responsive image generation.
- **Webhook Support**: Enable automatic updates triggered by repository changes.
- **Flexible Configuration**: Easily customizable through environment variables.
- **Markdown Support**: Native handling of Markdown files with front matter.
- **Tagging System**: Organize and filter content using a flexible tagging system.

## Requirements

- PHP 8.2+
- Composer
- Laravel 11.x
- PostgreSQL database (recommended for vector search capabilities)
- Git

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

Flatlayer CMS is primarily configured through environment variables. Copy the `.env.example` file to `.env` and customize the settings according to your needs. Here's an overview of key configuration options:

### Application Settings

```
APP_NAME=Flatlayer
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
```

- `APP_KEY`: Generate this using `php artisan key:generate`
- `APP_DEBUG`: Set to `false` in production
- `APP_URL`: Set to your application's URL

### Logging

```
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

Adjust `LOG_LEVEL` based on your environment (e.g., `error` for production).

### Database

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flatlayer
DB_USERNAME=
DB_PASSWORD=
```

Configure these settings to match your PostgreSQL database setup.

### Session and Cache

```
SESSION_DRIVER=database
CACHE_DRIVER=database
```

These settings use the database for session and cache storage. Adjust if needed.

### Queue

```
QUEUE_CONNECTION=database
```

Configure your preferred queue connection. The default uses the database.

### Filesystem

```
FILESYSTEM_DISK=local
```

Set your preferred filesystem disk. Options include `local`, `public`, `s3`, etc.

### Jina.ai Configuration

```
JINA_API_KEY=your_jina_api_key
JINA_RERANK_MODEL=jina-reranker-v2-base-multilingual
JINA_EMBED_MODEL=jina-embeddings-v2-base-en
```

- Obtain your Jina API key from [jina.ai](https://jina.ai/)
- The default models are set, but you can change them based on Jina AI documentation

### GitHub Webhook

```
GITHUB_WEBHOOK_SECRET=your_webhook_secret
```

Set this to match the secret you configure in your GitHub repository's webhook settings.

### Media Asset Configuration

```
FLATLAYER_MEDIA_USE_SIGNATURES=false
```

Set to `true` in production to use signed URLs for media assets.

### Content Sync Configuration

Configure your content sources:

```
FLATLAYER_SYNC_POSTS="path=/path/to/posts"
FLATLAYER_SYNC_PAGES="path=/path/to/pages --pattern=**/*.md"
```

Format: `FLATLAYER_SYNC_[TYPE]="path=/path/to/content --type=[type] --pattern=[glob_pattern]"`
- `--type` and `--pattern` are optional
- Default pattern is `**/*.md`

You can add multiple sync configurations for different content types.

## Usage

### Content Synchronization

Manually sync content:

```
php artisan flatlayer:entry-sync --type=posts
```

This can also be triggered automatically via webhook.

### Image Cache Management

Clear old image cache:

```
php artisan image:clear-cache [days]
```

### Content Querying

Use the powerful query language for filtering and searching:

```
GET /api/posts?filter={"title":{"$contains":"Laravel"},"tags":["tutorial"]}&search=eloquent
```

### Image Processing

Generate responsive images:

```html
<img src="{{ route('media.transform', ['id' => $imageId, 'w' => 800, 'h' => 600]) }}" alt="Responsive Image">
```

## Extending Flatlayer

### Custom Models

Extend the base model for new content types:

```php
use App\Models\Entry;

class CustomContent extends Entry
{
    // Custom implementation
}
```

### Custom Commands

Create new Artisan commands in `app/Console/Commands/`.

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

Thank you for using Flatlayer CMS! We hope it serves your content management needs effectively.
