# FlatLayer CMS

FlatLayer CMS is a Laravel-based flat-file content management system designed to provide a powerful query language for your GitHub-hosted documentation. It synchronizes your Markdown files from a GitHub repository and offers advanced search and filtering capabilities, making it ideal for managing and querying large documentation sets.

## Features

- Synchronization with GitHub repositories
- Markdown file parsing and front matter support
- Advanced query language for filtering and searching content
- Image processing and responsive image generation
- Webhook support for automatic updates
- Extensible model system for different content types
- Jina.ai integration for improved search result ranking

## Requirements

- PHP 8.2+
- Composer
- Laravel 11.x
- PostgreSQL database (recommended for vector search capabilities)
- Redis (optional, for caching)

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

### Database

FlatLayer CMS uses PostgreSQL by default for its vector search capabilities. Update your `.env` file with your database credentials:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Installing PG Vector Extension

To enable vector search capabilities in PostgreSQL, you need to install the PG Vector extension. Here are the steps for Ubuntu:

1. Install PostgreSQL development files:
   ```
   sudo apt-get install postgresql-server-dev-all
   ```

2. Clone the pgvector repository:
   ```
   git clone --branch v0.4.4 https://github.com/pgvector/pgvector.git
   ```

3. Build and install the extension:
   ```
   cd pgvector
   make
   sudo make install
   ```

4. Enable the extension in your database:
   ```sql
   CREATE EXTENSION vector;
   ```

For more detailed instructions or for other operating systems, please refer to the [official pgvector documentation](https://github.com/pgvector/pgvector).

### FlatLayer Configuration

Configure your models and repositories in the `config/flatlayer.php` file:

```php
return [
    'models' => [
        App\Models\Post::class => [
            'path' => '/path/to/your/markdown/files',
            'source' => '*.md',
            'hook' => 'https://your-webhook-url.com/posts',
        ],
        App\Models\Document::class => [
            'path' => '/path/to/your/documentation/files',
            'source' => '*.md',
            'hook' => 'https://your-webhook-url.com/documents',
        ],
    ],
    // ... other configurations
];
```

Note: The `'hook'` URL is an external webhook that can be used to trigger actions (such as rebuilding your frontend) after content is updated in FlatLayer CMS.

### GitHub Webhook

1. Set up a webhook in your GitHub repository settings.
2. Point the webhook to your application's webhook URL (e.g., `https://your-app.com/{modelSlug}/webhook`).
3. Set the `GITHUB_WEBHOOK_SECRET` in your `.env` file to match the secret you configured in GitHub.

### OpenAI Configuration

FlatLayer uses OpenAI for generating embeddings. Set your API key in the `.env` file:

```
OPENAI_API_KEY=your_openai_api_key
```

### Jina.ai Configuration

FlatLayer uses Jina.ai for reranking search results. To set this up:

1. Sign up for a Jina AI account at [https://jina.ai/](https://jina.ai/).
2. Navigate to your account settings and generate an API key.
3. Add the following to your `.env` file:

```
JINA_API_KEY=your_jina_api_key
JINA_MODEL=jina-reranker-v2-base-multilingual
```

You can adjust the `JINA_MODEL` value based on your specific needs. The default model works well for multi-language support.

## Usage

### Syncing Content

To manually sync content from your configured repositories:

```
php artisan flatlayer:markdown-sync {model}
```

Replace `{model}` with the model name (e.g., `Post` or `Document`).

### Querying Content

FlatLayer provides a powerful query language for filtering and searching content. Use the `/api/{modelSlug}/list` endpoint with query parameters:

```
GET /api/posts/list?filter={"title":{"$contains":"Laravel"},"tags":["tutorial"]}&search=eloquent
```

This example filters posts with "Laravel" in the title, tagged as "tutorial", and searches for the term "eloquent" using vectorized search.

For detailed information on the filtering and query language capabilities, please refer to our [Filtering Documentation](./docs/filtering.md).

### Image Processing

FlatLayer automatically processes images referenced in your Markdown files. Use the `media.transform` route to generate responsive images:

```
<img src="{{ route('media.transform', ['id' => $imageId, 'w' => 800, 'h' => 600]) }}" alt="Responsive Image">
```

## Extending FlatLayer

### Custom Models

Create new models that use the `MarkdownModel` and `Searchable` traits to add support for new content types:

```php
use App\Traits\MarkdownContentModel;
use App\Traits\Searchable;

class CustomContent extends Model
{
    use MarkdownContentModel, Searchable;

    // ... your model implementation
}
```

### Custom Filters

Extend the `QueryFilter` class to add custom filtering logic for your models.

## Testing

Run the test suite with:

```
php artisan test
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
