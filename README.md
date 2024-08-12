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

Flatlayer CMS is primarily configured through environment variables. Key configurations include:

### Database

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Jina.ai for Vector Search

```
JINA_API_KEY=your_jina_api_key
JINA_RERANK_MODEL=jina-reranker-v2-base-multilingual
JINA_EMBED_MODEL=jina-embeddings-v2-base-en
```

### GitHub Webhook

1. Set up a webhook in your GitHub repository settings.
2. Configure the secret in your `.env` file:
   ```
   GITHUB_WEBHOOK_SECRET=your_webhook_secret
   ```

### Content Sync

Define content sources in your `.env` file:

```
FLATLAYER_SYNC_POSTS="path=/path/to/posts"
FLATLAYER_SYNC_PAGES="path=/path/to/pages --pattern=**/*.md"
```

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
