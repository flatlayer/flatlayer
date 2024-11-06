# Flatlayer CMS

A powerful, API-first headless CMS built on Laravel that combines Git-based content management with AI-powered search capabilities. Flatlayer seamlessly integrates Markdown content from Git repositories while providing advanced querying, image processing, and vector search features through a clean REST API.

## ✨ Key Features

### Content Management
- **Git Integration**: Sync content directly from Git repositories with automatic updates via webhooks
- **Markdown + Front Matter**: Native support for Markdown files with YAML front matter
- **Flexible Content Types**: Support for multiple content types (posts, pages, docs, etc.)
- **Rich Media Handling**: Automatic image processing, optimization, and responsive image generation
- **Tagging System**: Organize and filter content with a flexible tagging system

### Search & Retrieval
- **AI-Powered Search**: Vector search using OpenAI embeddings for intelligent content discovery
- **Advanced Query Language**: Rich filtering with support for:
    - Complex nested queries
    - JSON field filtering
    - Tag-based filtering
    - Full-text search
    - Custom ordering
- **Field Selection**: GraphQL-like field selection to optimize response payload size
- **Built-in Pagination**: Efficient handling of large content sets

### Image Processing
- **On-the-fly Transformations**: Resize, crop, and optimize images via URL parameters
- **Multiple Formats**: Support for JPEG, PNG, WebP, and GIF
- **Automatic Optimization**: Built-in image optimization and caching
- **Thumbhash Generation**: Automatic generation of image previews

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- PostgreSQL 12+ (recommended for vector search)
- Git
- Composer
- PHP Extensions: gd, fileinfo, dom, libxml

### Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/flatlayer-cms.git
cd flatlayer-cms

# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Set up database
php artisan migrate

# Create storage link
php artisan storage:link
```

## ⚙️ Configuration

### Essential Environment Variables

```env
# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flatlayer
DB_USERNAME=your_username
DB_PASSWORD=your_password

# OpenAI Configuration (for vector search)
OPENAI_API_KEY=your_openai_key
OPENAI_ORGANIZATION=your_org_id
OPENAI_SEARCH_EMBEDDING_MODEL=text-embedding-3-small

# GitHub Webhook Configuration
GITHUB_WEBHOOK_SECRET=your_webhook_secret
```

### Content Source Configuration

Configure content sources using environment variables:

```env
# Example: Blog Posts Configuration
FLATLAYER_SYNC_POSTS_PATH="/path/to/posts"
FLATLAYER_SYNC_POSTS_PATTERN="*.md"
FLATLAYER_SYNC_POSTS_WEBHOOK="http://example.com/webhook/posts"
FLATLAYER_SYNC_POSTS_PULL=true

# Example: Documentation Pages Configuration
FLATLAYER_SYNC_DOCS_PATH="/path/to/docs"
FLATLAYER_SYNC_DOCS_PATTERN="**/*.md"
FLATLAYER_SYNC_DOCS_WEBHOOK="http://example.com/webhook/docs"
FLATLAYER_SYNC_DOCS_PULL=true
```

## 🔌 API Reference

### Content Endpoints

#### List Entries
```http
GET /entry/{type}?filter={filter}&fields={fields}&page={page}&per_page={per_page}
```

Query Parameters:
- `filter`: JSON string for filtering
- `fields`: JSON array of fields to retrieve
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15)

Example:
```http
GET /entry/posts?filter={"meta.category":{"$in":["tech","programming"]},"$tags":["tutorial"]}&fields=["title","excerpt","published_at"]
```

#### Get Single Entry
```http
GET /entry/{type}/{slug}?fields={fields}
```

#### Batch Retrieve Entries
```http
GET /entry/batch/{type}?slugs={slug1,slug2,slug3}&fields={fields}
```

### Image Transformation

```http
GET /image/{id}.{extension}?w={width}&h={height}&q={quality}&fm={format}
```

Parameters:
- `w`: Width in pixels
- `h`: Height in pixels
- `q`: Quality (1-100)
- `fm`: Format (jpg, png, webp)

## 🔍 Advanced Querying

### Filter Examples

```json
{
  "$or": [
    {
      "type": "post",
      "meta.category": "technology",
      "published_at": {
        "$gte": "2024-01-01"
      }
    },
    {
      "type": "tutorial",
      "$tags": ["programming", "beginner"],
      "meta.difficulty": {
        "$in": ["beginner", "intermediate"]
      }
    }
  ],
  "$search": "Laravel development",
  "$order": {
    "published_at": "desc"
  }
}
```

### Field Selection Examples

```json
[
  "id",
  "title",
  ["published_at", "date"],
  "meta.author",
  "meta.category",
  "tags",
  "images.featured"
]
```

## 🛠️ Development

```bash
# Run tests
composer test

# Format code
composer format

# Run static analysis
composer larastan
```

## 📖 Documentation

For detailed documentation on the Query Language, Content Sync Configuration, and API Usage, please visit our [Wiki](link-to-wiki).

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
