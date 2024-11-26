# Content Synchronization Guide

Flatlayer CMS provides a robust content synchronization system that automatically processes Markdown files from configured content repositories. This guide explains how content synchronization works and how to configure it properly.

## Overview

The content synchronization system:
- Automatically discovers and processes all `.md` files in configured directories
- Integrates with Laravel's filesystem abstraction for flexible storage options
- Supports local directories and S3 storage
- Provides Git integration for version control
- Includes webhook support for automated updates

## Configuration

### Repository Configuration

Flatlayer uses environment variables to configure content repositories. Each repository requires at minimum a path configuration:

```env
# Basic local repository
CONTENT_REPOSITORY_DOCS_PATH=/path/to/docs
CONTENT_REPOSITORY_DOCS_DRIVER=local

# S3-based repository
CONTENT_REPOSITORY_BLOG_PATH=posts
CONTENT_REPOSITORY_BLOG_DRIVER=s3
CONTENT_REPOSITORY_BLOG_KEY=your-aws-key
CONTENT_REPOSITORY_BLOG_SECRET=your-aws-secret
CONTENT_REPOSITORY_BLOG_REGION=us-west-2
CONTENT_REPOSITORY_BLOG_BUCKET=your-bucket
```

Available configuration options:
- `PATH`: Directory path (required)
- `DRIVER`: Storage driver (`local` or `s3`, defaults to `local`)
- `WEBHOOK_URL`: URL to notify after sync completion (optional)
- `PULL`: Whether to pull Git changes before sync (optional, defaults to false)

Additional S3-specific options:
- `KEY`: AWS access key ID
- `SECRET`: AWS secret access key
- `REGION`: AWS region
- `BUCKET`: S3 bucket name
- `URL`: Custom S3 URL (optional)
- `ENDPOINT`: Custom S3 endpoint (optional)
- `USE_PATH_STYLE_ENDPOINT`: Use path-style endpoints (optional)

### Git Integration

To use Git integration, configure the authentication settings:

```env
# Git Configuration
GITHUB_WEBHOOK_SECRET=your_webhook_secret

# Git Authentication Settings
FLATLAYER_GIT_AUTH_METHOD=token
FLATLAYER_GIT_USERNAME=your_username
FLATLAYER_GIT_TOKEN=your_token

# OR for SSH authentication
FLATLAYER_GIT_AUTH_METHOD=ssh
FLATLAYER_GIT_SSH_KEY_PATH=/path/to/ssh/key

# Additional Git settings
FLATLAYER_GIT_COMMIT_NAME="Flatlayer CMS"
FLATLAYER_GIT_COMMIT_EMAIL=cms@flatlayer.io
FLATLAYER_GIT_TIMEOUT=60
```

## Content Processing

### File Discovery

Flatlayer automatically:
- Recursively scans configured directories for `.md` files
- Handles nested directory structures
- Maintains proper ordering with index files
- Resolves slug conflicts

For example, given this structure:
```
docs/
├── index.md
├── getting-started/
│   ├── index.md
│   ├── installation.md
│   └── configuration.md
└── advanced/
    ├── index.md
    └── deployment.md
```

The system will:
1. Process all `.md` files
2. Create appropriate slugs (`docs`, `docs/getting-started`, etc.)
3. Handle index files properly (`index.md` becomes parent slug)
4. Maintain hierarchical relationships

### Synchronization Methods

#### Manual Sync

Use the Artisan command:

```bash
# Basic sync
php artisan flatlayer:sync --type=docs

# Advanced usage
php artisan flatlayer:sync --type=docs \
  --disk=custom.disk \
  --pull \
  --skip \
  --dispatch \
  --webhook=http://example.com/webhook
```

Command options:
- `--type`: Repository type to sync (required)
- `--disk`: Override the configured disk
- `--pull`: Pull latest Git changes
- `--skip`: Skip if no changes detected
- `--dispatch`: Run in background queue
- `--webhook`: Custom webhook URL

#### GitHub Webhook Integration

1. Add webhook in GitHub repository settings:
    - URL: `https://your-domain.com/webhooks/{type}`
    - Content type: `application/json`
    - Secret: Your `GITHUB_WEBHOOK_SECRET` value
    - Events: Push event only

2. Configure webhook in your environment:
```env
# Required for webhook authentication
GITHUB_WEBHOOK_SECRET=your_secret

# Repository configuration
CONTENT_REPOSITORY_DOCS_WEBHOOK_URL=https://example.com/webhooks/callback
CONTENT_REPOSITORY_DOCS_PULL=true
```

When a webhook is received:
1. Signature is verified using webhook secret
2. Changes are pulled if `PULL=true`
3. Content is synchronized
4. Webhook URL is notified if configured

## Best Practices

### Repository Organization

- Use consistent directory structure
- Place related content in subdirectories
- Use meaningful file names
- Keep media files near their content
- Use index.md for section landing pages

### Performance

- Use background queues for large repositories
- Set appropriate Git timeouts
- Configure webhook retry settings

### Security

- Use secure webhook secrets
- Use read-only Git tokens
- Configure appropriate S3 bucket permissions
- Regularly rotate credentials

## Troubleshooting

### Common Issues

1. **File Permission Problems**
    - Check directory permissions
    - Verify Laravel worker permissions
    - Ensure Git can access repositories

2. **Webhook Failures**
    - Verify webhook secret
    - Check GitHub webhook logs
    - Monitor Laravel logs
    - Confirm correct content type

3. **Git Integration Issues**
    - Validate authentication settings
    - Check SSH key permissions
    - Verify repository access
    - Monitor timeout settings

4. **Content Processing Errors**
    - Validate front matter syntax
    - Check file encodings
    - Verify media file paths
    - Monitor Laravel logs

### Configuration Settings

These are all available configuration options from `config/flatlayer.php`:

```php
'sync' => [
    // Default sync settings
    'default_pattern' => env('FLATLAYER_SYNC_DEFAULT_PATTERN', '*.md'),
    'batch_size' => env('FLATLAYER_SYNC_BATCH_SIZE', 100),
    'log_level' => env('FLATLAYER_SYNC_LOG_LEVEL', 'info'),
],

'git' => [
    'auth_method' => env('FLATLAYER_GIT_AUTH_METHOD', 'token'),
    'username' => env('FLATLAYER_GIT_USERNAME'),
    'token' => env('FLATLAYER_GIT_TOKEN'),
    'ssh_key_path' => env('FLATLAYER_GIT_SSH_KEY_PATH'),
    'commit_name' => env('FLATLAYER_GIT_COMMIT_NAME', 'Flatlayer CMS'),
    'commit_email' => env('FLATLAYER_GIT_COMMIT_EMAIL', 'cms@flatlayer.io'),
    'timeout' => env('FLATLAYER_GIT_TIMEOUT', 60),
    'retry_attempts' => env('FLATLAYER_GIT_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('FLATLAYER_GIT_RETRY_DELAY', 5),
],

'github' => [
    'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
],
```
