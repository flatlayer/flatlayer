# Flatlayer CMS

Flatlayer CMS is a simple, Git-based content management system built on Laravel. It offers powerful features like AI-powered vector search and advanced query capabilities, making it ideal for managing and searching large documentation sets or content repositories.

## Key Features

- Git-based content synchronization
- AI-powered vector search using Jina.ai
- Advanced query language for content filtering
- Image processing and caching
- Webhook support for automatic updates
- Configurable via environment variables

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

3. Copy the `.env.example` file to `.env` and configure your environment variables:
   ```
   cp .env.example .env
   ```

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

Flatlayer CMS is primarily configured through environment variables. Refer to the `.env.example` file for a complete list of configuration options. Here are some key configurations:

### Database

Update your `.env` file with your PostgreSQL database credentials:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Jina.ai Configuration

Flatlayer uses Jina.ai for vector search and result reranking. Add the following to your `.env` file:

```
JINA_API_KEY=your_jina_api_key
JINA_RERANK_MODEL=jina-reranker-v2-base-multilingual
JINA_EMBED_MODEL=jina-embeddings-v2-base-en
```

### GitHub Webhook

To enable automatic updates via GitHub webhooks:

1. Set up a webhook in your GitHub repository settings.
2. Set the `GITHUB_WEBHOOK_SECRET` in your `.env` file to match the secret you configured in GitHub.

### Content Sync Configuration

Configure your content synchronization settings in the `.env` file:

```
FLATLAYER_SYNC_POSTS="path=/path/to/posts"
FLATLAYER_SYNC_PAGES="path=/path/to/pages --pattern=**/*.md"
```

You can add multiple sync configurations for different content types.

## Usage

### Syncing Content

To manually sync content from your configured repositories:

```
php artisan flatlayer:entry-sync --type=posts
```

This command should be run whenever your Git repository is updated. It can also be triggered automatically via a webhook.

### Clearing Image Cache

Run the following command daily to remove old image cache:

```
php artisan image:clear-cache
```

You can adjust the number of days after which to clear cache by passing an argument:

```
php artisan image:clear-cache 7
```

### Querying Content

Flatlayer provides a powerful query language for filtering and searching content. Use the API endpoints with query parameters:

```
GET /api/posts?filter={"title":{"$contains":"Laravel"},"tags":["tutorial"]}&search=eloquent
```

This example filters posts with "Laravel" in the title, tagged as "tutorial", and searches for the term "eloquent" using vector search.

### Image Processing

Flatlayer automatically processes images referenced in your content. Use the `media.transform` route to generate responsive images:

```
<img src="{{ route('media.transform', ['id' => $imageId, 'w' => 800, 'h' => 600]) }}" alt="Responsive Image">
```

## Extending Flatlayer

### Custom Models

Create new models that extend the base Flatlayer model to add support for new content types:

```php
use App\Models\FlatlayerModel;

class CustomContent extends FlatlayerModel
{
    // ... your model implementation
}
```

### Custom Commands

You can create custom Artisan commands to extend Flatlayer's functionality. Place your command classes in the `app/Console/Commands` directory.

## Testing

Run the test suite with:

```
php artisan test
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
