# Markdown Sync Process Guide

FlatLayer CMS provides a powerful synchronization mechanism to keep your content up-to-date with your GitHub repository. This guide explains how the markdown synchronization process works, including the GitHub webhook integration and the `MarkdownSyncJob`.

## Overview

The markdown sync process allows you to automatically update your FlatLayer CMS content whenever changes are pushed to your GitHub repository. This process involves several components:

1. GitHub Webhook
2. Webhook Controller
3. ProcessGitHubWebhookJob
4. MarkdownSyncJob

## GitHub Webhook Setup

1. In your GitHub repository, go to Settings > Webhooks > Add webhook.
2. Set the Payload URL to `https://your-app-url.com/{modelSlug}/webhook`.
3. Set the Content type to `application/json`.
4. Set a Secret key (you'll need this for your FlatLayer configuration).
5. Choose which events should trigger the webhook (usually just the `push` event).

## Webhook Controller

The `GitHubWebhookController` handles incoming webhook requests:

1. It verifies the webhook signature using the secret key you set in GitHub.
2. It resolves the appropriate model based on the `{modelSlug}` in the URL.
3. If everything is valid, it dispatches a `ProcessGitHubWebhookJob`.

## ProcessGitHubWebhookJob

This job is responsible for processing the webhook payload:

1. It uses the `Git` library to pull the latest changes from the repository.
2. If changes are detected, it triggers the `MarkdownSyncJob`.

## MarkdownSyncJob

The `MarkdownSyncJob` is the core of the synchronization process:

1. It scans the configured directory for markdown files.
2. For each file:
    - If it's a new file, a new model instance is created.
    - If it's an existing file, the corresponding model is updated.
    - If a file has been deleted, the corresponding model is deleted.
3. It processes front matter, content, and associated media files.
4. After syncing, it can trigger a webhook to rebuild your frontend (if configured).

## Manual Sync

You can also trigger a manual sync using the Artisan command:

```
php artisan flatlayer:markdown-sync {model}
```

Replace `{model}` with the name of your model (e.g., `Post` or `Document`).

## Configuration

The sync process is configured in `config/flatlayer.php`:

```php
'models' => [
    App\Models\Post::class => [
        'path' => '/path/to/your/markdown/files',
        'source' => '*.md',
        'hook' => 'https://your-webhook-url.com/posts',
    ],
    // ... other models
],
```

- `path`: The directory where your markdown files are located.
- `source`: The glob pattern to match markdown files.
- `hook`: An optional webhook URL to trigger after syncing (e.g., to rebuild your frontend).

## Best Practices

1. Use meaningful commit messages in GitHub, as they can be used to track changes.
2. Organize your markdown files in a logical directory structure.
3. Use front matter to include metadata about your content.
4. Regularly backup your database, especially before large synchronization operations.

By understanding and properly configuring the markdown sync process, you can ensure that your FlatLayer CMS always reflects the latest content from your GitHub repository.
