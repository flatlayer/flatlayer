# Flatlayer CMS

Flatlayer is a Git-native headless CMS that brings together the simplicity of Markdown content with the power of AI-driven search. Built on Laravel, it provides a clean REST API for advanced content querying, dynamic image processing, and seamless Git integration - all designed to make content management feel natural for developers.

## ✨ Key Features

### Content Management
- **Git Integration**: Sync content directly from Git repositories with automatic updates via webhooks
- **Markdown + Front Matter**: Native support for Markdown files with YAML front matter
- **Hierarchical Content**: Built-in support for nested content with automatic path-based navigation
- **Flexible Content Types**: Support for multiple content types (posts, pages, docs, etc.)

### Media Handling
- **Image Processing**: Dynamic image transformation and optimization
- **Responsive Images**: Dynamic image resizing for different display sizes
- **Format Conversion**: Support for JPEG, PNG, WebP, and GIF output formats
- **Thumbhash Generation**: Automatic generation of image previews

### Advanced Features
- **AI-Powered Search**: Vector search using OpenAI embeddings for intelligent content discovery
- **Advanced Query Language**: Rich filtering with support for complex nested queries and JSON fields
- **Tagging System**: Organize and filter content with a flexible tagging system
- **Navigation Systems**: Both chronological and hierarchical content navigation

## 🚀 Prerequisites

- PHP 8.2+
- PostgreSQL 12+ (recommended for vector search) or SQLite
- Git
- Composer
- PHP Extensions:
    - gd (image processing)
    - fileinfo (mime type detection)
    - dom (XML/HTML processing)
    - libxml (XML processing)
    - intl (internationalization)
    - json (JSON processing)
- For vector search: PostgreSQL with pgvector extension

## ⚡ Installation

```bash
# Clone the repository
git clone https://github.com/flatlayer/flatlayer.git
cd flatlayer-cms

# Install dependencies
composer install

# Run the interactive setup wizard
php artisan flatlayer:setup
```

## 🪄 Setup Wizard

The interactive setup wizard guides you through the configuration process:

```bash
php artisan flatlayer:setup
```

Options:
- `--quick`: Skip optional configurations
- `--force`: Force setup even if already configured
- `--env=path/to/.env`: Specify a custom .env file location

The wizard handles:
- Database configuration
- OpenAI API setup
- Content repository configuration
- Git webhook setup
- Image processing settings
- Cache and queue configuration

## ⚙️ Configuration

### Essential Configuration

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

# Git Authentication
FLATLAYER_GIT_AUTH_METHOD=token
FLATLAYER_GIT_USERNAME=your_username
FLATLAYER_GIT_TOKEN=your_token

# Image Processing
FLATLAYER_MEDIA_USE_SIGNATURES=true
FLATLAYER_MEDIA_MAX_WIDTH=8192
FLATLAYER_MEDIA_MAX_HEIGHT=8192
```

### Repository Configuration

Support for both local and S3 storage:

```env
# Local Repository
CONTENT_REPOSITORY_POSTS_PATH=/path/to/posts
CONTENT_REPOSITORY_POSTS_DRIVER=local
CONTENT_REPOSITORY_POSTS_WEBHOOK_URL=http://example.com/webhook/posts
CONTENT_REPOSITORY_POSTS_PULL=true

# S3 Repository
CONTENT_REPOSITORY_DOCS_PATH=/path/to/docs
CONTENT_REPOSITORY_DOCS_DRIVER=s3
CONTENT_REPOSITORY_DOCS_KEY=aws-key
CONTENT_REPOSITORY_DOCS_SECRET=aws-secret
CONTENT_REPOSITORY_DOCS_REGION=us-west-2
CONTENT_REPOSITORY_DOCS_BUCKET=your-bucket
```

## 📂 Content Structure

Content files should be Markdown files with YAML front matter:

```markdown
---
title: My First Post
type: post
published_at: 2024-01-01
tags: [tutorial, beginner]
images:
  featured: featured.jpg
  gallery: [image1.jpg, image2.jpg]
meta:
  author: John Doe
  category: tutorials
  difficulty: beginner
  nav_order: 1
---

# My First Post

Content goes here...
```

### Hierarchical Structure

Support for nested content with index files:

```
docs/
├── index.md              # /docs
├── getting-started/
│   ├── index.md         # /docs/getting-started
│   ├── installation.md  # /docs/getting-started/installation
│   └── configuration.md # /docs/getting-started/configuration
└── advanced/
    ├── index.md         # /docs/advanced
    └── deployment.md    # /docs/advanced/deployment
```

## 🔌 API Reference

### List Entries
```http
GET /entry/{type}?filter={filter}&fields={fields}&page={page}&per_page={per_page}
```

Query Parameters:
- `filter`: JSON string for filtering
- `fields`: JSON array of fields to retrieve
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15)

### Get Single Entry
```http
GET /entry/{type}/{slug}?fields={fields}&includes=hierarchy,sequence,timeline
```

### Batch Retrieve Entries
```http
GET /entry/batch/{type}?slugs={slug1,slug2,slug3}&fields={fields}
```

### Get Content Hierarchy
```http
GET /hierarchy/{type}?root={root}&depth={depth}&fields={fields}
```

### Image Transformation
```http
GET /image/{id}.{extension}?w={width}&h={height}&q={quality}&fm={format}
```

## 🔍 Query Examples

### Complex Filter
```http
GET /entry/post?filter={
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
    "$hierarchy": {
        "descendants": "docs/tutorials"
    },
    "$search": "Laravel development",
    "$order": {
        "published_at": "desc"
    }
}
```

### Field Selection
```http
GET /entry/post?fields=[
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

# Clear image cache
php artisan image:clear-cache

# Sync content repositories
php artisan flatlayer:sync --type=docs

# Sync with Git pull
php artisan flatlayer:sync --type=docs --pull

# Queue sync job
php artisan flatlayer:sync --type=docs --dispatch
```

## 📚 Documentation

- [Content Synchronization](./docs/backend/syncing.md)
- [Storage System](./docs/backend/storage.md)
- [Search Implementation](./docs/backend/search.md)
- [Image Transformation](./docs/backend/image-transformation.md)
- [Filtering API](./docs/backend/filtering.md)
- [Field Selection](./docs/backend/field-selection.md)
- [Pagination](./docs/backend/pagination.md)
- [Content Processing](./docs/backend/processing.md)
- [OpenAPI Specification](./docs/api/openapi.yaml)

## 🤝 Contributing

Please see our [Contributing Guide](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## 📄 License

This project is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
