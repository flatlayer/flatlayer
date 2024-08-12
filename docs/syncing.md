# Markdown Sync Process Guide

Flatlayer CMS provides a flexible synchronization mechanism to keep your content up-to-date with your content sources. This guide explains how the markdown synchronization process works, focusing on the SyncConfigurationService and environment-based configuration.

## Overview

The markdown sync process allows you to automatically update your Flatlayer CMS content from various sources. This process now primarily involves:

1. SyncConfigurationService
2. Environment-based Configuration
3. GitHub Webhook (optional)
4. EntrySyncJob

## SyncConfigurationService

The SyncConfigurationService is the core component of the new sync process. It's responsible for:

1. Loading sync configurations from environment variables
2. Parsing these configurations into a structured format
3. Providing methods to access and manage these configurations

## Environment-based Configuration

Sync configurations are now stored in environment variables using the following format:

```
FLATLAYER_SYNC_{TYPE}="path/to/content --pattern=*.md"
```

Where `{TYPE}` is the content type (e.g., POST, PAGE, etc.), and the value contains the path and optional pattern for file matching.

Example:
```
FLATLAYER_SYNC_POST="/path/to/posts --pattern=**/*.md"
FLATLAYER_SYNC_PAGE="/path/to/pages"
```

## Configuration Options

Each sync configuration can include:

- `path`: The directory where your markdown files are located (required).
- `--pattern`: The glob pattern to match markdown files (optional, defaults to `**/*.md`).

## GitHub Webhook Setup (Optional)

If you're using GitHub as your content source:

1. In your GitHub repository, go to Settings > Webhooks > Add webhook.
2. Set the Payload URL to `https://your-app-url.com/webhook/{type}`.
3. Set the Content type to `application/json`.
4. Set a Secret key (you'll need to add this to your `.env` file as `GITHUB_WEBHOOK_SECRET`).
5. Choose which events should trigger the webhook (usually just the `push` event).

## EntrySyncJob

The `EntrySyncJob` is responsible for the actual synchronization process:

1. It uses the configurations provided by SyncConfigurationService.
2. For each configured content type:
    - It scans the specified directory for markdown files matching the given pattern.
    - Creates new entries for new files.
    - Updates existing entries for modified files.
    - Deletes entries for removed files.
3. It processes front matter, content, and associated media files.

## Manual Sync

You can trigger a manual sync using the Artisan command:

```
php artisan flatlayer:entry-sync --type={type}
```

Replace `{type}` with the type of content you want to sync (e.g., `post` or `page`).

You can also specify a path directly:

```
php artisan flatlayer:entry-sync {path}
```

## Additional Options

The `flatlayer:entry-sync` command supports several options:

- `--pull`: Pull latest changes from Git repository before syncing
- `--skip`: Skip syncing if no changes are detected
- `--dispatch`: Dispatch the job to the queue instead of running it immediately

## Best Practices

1. Use meaningful commit messages in your content repository, as they can be used to track changes.
2. Organize your markdown files in a logical directory structure.
3. Use front matter to include metadata about your content.
4. Regularly backup your database, especially before large synchronization operations.
5. Use environment-specific configurations for different deployment environments.

## Troubleshooting

If you encounter issues with the sync process:

1. Check your environment variables to ensure they're correctly set.
2. Verify file permissions for the configured content directories.
3. Check the Laravel logs for any error messages.
4. Ensure your webhook secret (if using GitHub) is correctly set in both GitHub and your `.env` file.

By understanding and properly configuring the markdown sync process, you can ensure that your Flatlayer CMS always reflects the latest content from your designated sources.
