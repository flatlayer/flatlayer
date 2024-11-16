# Markdown Sync Process Guide

Flatlayer CMS provides a flexible synchronization mechanism to keep your content up-to-date with your content sources. This guide explains how the markdown synchronization process works, focusing on the SyncConfigurationService, environment-based configuration, and Git integration.

## Overview

The markdown sync process allows you to automatically update your Flatlayer CMS content from various sources. This process involves:

1. SyncConfigurationService for managing sync configurations
2. Environment-based Configuration for easy setup
3. Git Integration for version control (optional)
4. GitHub Webhook support for automatic updates (optional)
5. EntrySyncJob for reliable processing

## Environment-based Configuration

Sync configurations are stored in environment variables using the following format:

```
FLATLAYER_SYNC_{TYPE}_{SETTING}="value"
```

Where:
- `{TYPE}` is the content type (e.g., POSTS, PAGES, etc.)
- `{SETTING}` is one of PATH, PATTERN, WEBHOOK, or PULL

Example configuration:
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

## Git Integration

Flatlayer CMS supports two methods of Git authentication:

### Token-based Authentication

```env
FLATLAYER_GIT_AUTH_METHOD=token
FLATLAYER_GIT_USERNAME=your_username
FLATLAYER_GIT_TOKEN=your_token
```

### SSH-based Authentication

```env
FLATLAYER_GIT_AUTH_METHOD=ssh
FLATLAYER_GIT_SSH_KEY_PATH=/path/to/key
```

Additional Git configuration options:
```env
FLATLAYER_GIT_COMMIT_NAME="Flatlayer CMS"
FLATLAYER_GIT_COMMIT_EMAIL=cms@flatlayer.io
FLATLAYER_GIT_TIMEOUT=60
FLATLAYER_GIT_RETRY_ATTEMPTS=3
FLATLAYER_GIT_RETRY_DELAY=5
```

## GitHub Webhook Setup

To enable automatic content updates when your repository changes:

1. Go to your GitHub repository's Settings > Webhooks > Add webhook
2. Configure the webhook:
    - Payload URL: `https://your-domain.com/webhook/{type}`
    - Content Type: `application/json`
    - Secret: Generate a secure secret and add it to your `.env`:
      ```env
      GITHUB_WEBHOOK_SECRET=your_webhook_secret
      ```
    - Events: Select "Just the push event"

## Content Synchronization

### Manual Sync

Use the Artisan command to trigger a manual sync:

```bash
# Basic sync
php artisan flatlayer:sync --type=posts

# Advanced options
php artisan flatlayer:sync --type=posts \
  --path=/custom/path \
  --pattern="**/*.md" \
  --pull \
  --skip \
  --dispatch \
  --webhook=http://example.com/webhook
```

Command options:
- `--type`: Content type to sync (required)
- `--path`: Override the configured content path
- `--pattern`: Override the file matching pattern
- `--pull`: Pull latest changes from Git repository
- `--skip`: Skip sync if no changes detected
- `--dispatch`: Run sync in background queue
- `--webhook`: Trigger webhook after sync completion

### Automatic Sync via Webhooks

When configured, the GitHub webhook will automatically trigger a sync when changes are pushed to your repository. The sync process:

1. Verifies the webhook signature using your `GITHUB_WEBHOOK_SECRET`
2. Pulls the latest changes from your repository (if `PULL=true`)
3. Processes all Markdown files matching your configured pattern
4. Triggers your configured webhook URL after completion (if set)

## Content Processing

The sync process handles:

### Markdown Files
- Processes YAML front matter for metadata
- Extracts titles and slugs
- Parses Markdown content
- Handles draft/published status
- Supports MDX-style components within content

### Media Files
- Processes images referenced in Markdown
- Generates optimized versions and thumbnails
- Handles image collections defined in front matter
- Creates thumbhash previews for progressive loading
- Supports responsive image sizes

### Tags and Categories
- Extracts and syncs tags from front matter
- Maintains tag relationships
- Updates tag counts and metadata
- Supports both simple and nested taxonomies

## Best Practices

1. **Content Organization**
    - Use consistent file naming conventions
    - Organize files in logical directories
    - Keep media files close to their content
    - Use descriptive image filenames

2. **Git Usage**
    - Use meaningful commit messages
    - Keep repositories focused on specific content types
    - Consider using branches for draft content
    - Regular backups of your database
    - Use `.gitignore` for excluding generated files

3. **Configuration Management**
    - Use environment-specific configs
    - Securely manage webhook secrets
    - Monitor webhook logs
    - Set appropriate timeouts for large repos
    - Regularly rotate webhook secrets

4. **Performance Optimization**
    - Use appropriate file patterns
    - Implement caching strategies
    - Configure queue workers for background processing
    - Monitor sync job durations
    - Use batch processing for large imports

## Troubleshooting

1. **Webhook Issues**
    - Verify webhook secret in both GitHub and `.env`
    - Check webhook payload delivery in GitHub
    - Ensure correct content type (`application/json`)
    - Monitor Laravel logs for webhook errors
    - Check webhook IP allowlist settings

2. **Git Integration Issues**
    - Verify authentication credentials
    - Check file permissions
    - Ensure SSH keys are properly configured
    - Monitor Git operation timeouts
    - Verify Git LFS settings if using large media files

3. **Sync Issues**
    - Check file permissions on content directories
    - Verify file patterns are correct
    - Monitor Laravel logs for sync errors
    - Check queue worker status if using dispatch
    - Verify database connection settings

4. **Content Processing Issues**
    - Validate front matter syntax
    - Check image file paths
    - Verify character encoding
    - Monitor media processing errors
    - Check image optimization settings

## Advanced Configuration

### Sync Options

```env
# Default sync settings
FLATLAYER_SYNC_DEFAULT_PATTERN="*.md"
FLATLAYER_SYNC_BATCH_SIZE=100
FLATLAYER_SYNC_CACHE_DURATION=3600
FLATLAYER_SYNC_LOG_LEVEL=info
```

### Queue Configuration

For background processing:
```env
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=90
```

### Error Handling

Configure error reporting:
```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
LOG_STACK=daily
```

### Vector Search Configuration

For content search functionality:
```env
OPENAI_API_KEY=your_api_key
OPENAI_ORGANIZATION=your_org_id
OPENAI_SEARCH_EMBEDDING_MODEL=text-embedding-3-small
```

By following these guidelines and properly configuring the markdown sync process, you can ensure that your Flatlayer CMS always reflects the latest content from your designated sources while maintaining reliability and performance.
