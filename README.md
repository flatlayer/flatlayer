# Flatlayer CMS

A powerful, API-first headless CMS built on Laravel that combines Git-based content management with AI-powered search capabilities. Flatlayer seamlessly integrates Markdown content from Git repositories while providing advanced querying, image processing, and vector search features through a clean REST API.

## ✨ Key Features

### Backend Features
- **Git Integration**: Sync content directly from Git repositories with automatic updates via webhooks
- **Markdown + Front Matter**: Native support for Markdown files with YAML front matter
- **Flexible Content Types**: Support for multiple content types (posts, pages, docs, etc.)
- **Rich Media Handling**: Automatic image processing, optimization, and responsive image generation
- **Tagging System**: Organize and filter content with a flexible tagging system
- **Advanced Query Language**: Rich filtering with support for complex nested queries and JSON fields
- **AI-Powered Search**: Vector search using OpenAI embeddings for intelligent content discovery

### Frontend SDK Features
- **Responsive Images**: Built-in support for responsive images with automatic size calculation
- **Markdown Parsing**: Parse and render Markdown content with embedded components
- **Svelte Components**: Pre-built Svelte components for common use cases
- **Search Integration**: Seamless integration with the backend's AI-powered search
- **TypeScript Support**: Full TypeScript definitions for better development experience

## 🚀 Quick Start

### Backend Prerequisites
- PHP 8.2+
- PostgreSQL 12+ (recommended for vector search) or SQLite
- Git
- Composer
- PHP Extensions: gd, fileinfo, dom, libxml

### Frontend Prerequisites
- Node.js 18+
- npm or yarn

### Backend Installation

```bash
# Clone the repository
git clone https://github.com/flatlayer/flatlayer.git
cd flatlayer-cms

# Install dependencies
composer install

# Run the interactive setup wizard
php artisan flatlayer:setup
```

### Frontend SDK Installation

```bash
npm install flatlayer-sdk
# or
yarn add flatlayer-sdk
```

## 🪄 Setup Wizard

Flatlayer includes an interactive setup wizard that guides you through the configuration process:

```bash
php artisan flatlayer:setup
```

Options:
- `--quick`: Skip optional configurations
- `--force`: Force setup even if already configured
- `--env=path/to/.env`: Specify a custom .env file location

## ⚙️ Configuration

### Backend Configuration

Essential environment variables:

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

Content source configuration:

```env
# Blog Posts Configuration
FLATLAYER_SYNC_POSTS_PATH="/path/to/posts"
FLATLAYER_SYNC_POSTS_PATTERN="*.md"
FLATLAYER_SYNC_POSTS_WEBHOOK="http://example.com/webhook/posts"
FLATLAYER_SYNC_POSTS_PULL=true

# Documentation Pages Configuration
FLATLAYER_SYNC_DOCS_PATH="/path/to/docs"
FLATLAYER_SYNC_DOCS_PATTERN="**/*.md"
FLATLAYER_SYNC_DOCS_WEBHOOK="http://example.com/webhook/docs"
FLATLAYER_SYNC_DOCS_PULL=true
```

### Frontend SDK Setup

```javascript
import Flatlayer from 'flatlayer-sdk';

const flatlayer = new Flatlayer('https://api.yourflatlayerinstance.com');
```

For Svelte applications:

```javascript
// Import pre-built components
import { ResponsiveImage, Markdown, SearchModal } from 'flatlayer-sdk/svelte';
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
---

# My First Post

Content goes here...
```

## 🔌 API Reference

### Backend API Endpoints

#### List Entries
```http
GET /entry/{type}?filter={filter}&fields={fields}&page={page}&per_page={per_page}
```

Query Parameters:
- `filter`: JSON string for filtering
- `fields`: JSON array of fields to retrieve
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15)

#### Get Single Entry
```http
GET /entry/{type}/{slug}?fields={fields}
```

#### Batch Retrieve Entries
```http
GET /entry/batch/{type}?slugs={slug1,slug2,slug3}&fields={fields}
```

#### Image Transformation
```http
GET /image/{id}.{extension}?w={width}&h={height}&q={quality}&fm={format}
```

### Frontend SDK Usage

#### Basic Usage
```javascript
// Fetching entries
const entries = await flatlayer.getEntryList('post', {
  filter: { status: 'published' },
  fields: ['title', 'excerpt', 'author']
});

// Retrieving a single entry
const post = await flatlayer.getEntry('post', 'my-first-post', [
  'title',
  'content',
  'meta'
]);

// Performing a search
const results = await flatlayer.search('JavaScript', 'post', {
  fields: ['title', 'excerpt', 'author']
});
```

#### Using Svelte Components

```svelte
<script>
import { ResponsiveImage, Markdown } from 'flatlayer-sdk/svelte';
import { flatlayer } from './flatlayer-instance';

export let post;
</script>

{#if post}
  <h1>{post.title}</h1>
  <ResponsiveImage
    baseUrl={flatlayer.baseUrl}
    imageData={post.featured_image}
    sizes={['100vw', 'md:50vw', 'lg:33vw']}
  />
  <Markdown content={post.content} />
{/if}
```

## 🔍 Advanced Querying

### Filter Examples

```javascript
const filter = {
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
};

const results = await flatlayer.getEntryList('post', { filter });
```

### Field Selection Examples

```javascript
const fields = [
  "id",
  "title",
  ["published_at", "date"],
  "meta.author",
  "meta.category",
  "tags",
  "images.featured"
];

const post = await flatlayer.getEntry('post', 'my-post', fields);
```

## 🛠️ Development

### Backend Development

```bash
# Run tests
composer test

# Format code
composer format

# Run static analysis
composer larastan
```

### Frontend Development

```bash
# Run tests
npm test

# Build the SDK
npm run build

# Run type checking
npm run typecheck
```

## 📚 Documentation

- [Backend Documentation](./docs/backend/README.md)
- [Frontend SDK Documentation](./docs/frontend/README.md)
- [API Reference](./docs/api/README.md)
- [Content Management Guide](./docs/content/README.md)

## 🤝 Contributing

Please see our [Contributing Guide](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## 📄 License

This project is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
