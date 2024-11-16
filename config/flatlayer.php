<?php

return [
    /**
     * Search configuration
     */
    'search' => [
        'openai' => [
            'embedding' => env('OPENAI_SEARCH_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
    ],

    /**
     * Image processing configuration
     */
    'images' => [
        'use_signatures' => env('FLATLAYER_MEDIA_USE_SIGNATURES', false),
        'max_width' => env('FLATLAYER_MEDIA_MAX_WIDTH', 8192),
        'max_height' => env('FLATLAYER_MEDIA_MAX_HEIGHT', 8192),
    ],

    /**
     * GitHub webhook configuration
     */
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    /**
     * Git repository authentication configuration
     *
     * Supports multiple authentication methods:
     * - token: Personal Access Token authentication (GitHub, GitLab, etc.)
     * - ssh: SSH key-based authentication
     */
    'git' => [
        // Authentication method ('token' or 'ssh')
        'auth_method' => env('FLATLAYER_GIT_AUTH_METHOD', 'token'),

        // Token-based authentication
        'username' => env('FLATLAYER_GIT_USERNAME'),
        'token' => env('FLATLAYER_GIT_TOKEN'),

        // SSH-based authentication
        'ssh_key_path' => env('FLATLAYER_GIT_SSH_KEY_PATH'),

        // Repository configuration
        'commit_name' => env('FLATLAYER_GIT_COMMIT_NAME', 'Flatlayer CMS'),
        'commit_email' => env('FLATLAYER_GIT_COMMIT_EMAIL', 'cms@flatlayer.io'),

        // Advanced options
        'timeout' => env('FLATLAYER_GIT_TIMEOUT', 60),
        'retry_attempts' => env('FLATLAYER_GIT_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('FLATLAYER_GIT_RETRY_DELAY', 5),
    ],

    /**
     * Content synchronization configuration
     */
    'sync' => [
        // Default sync settings
        'default_pattern' => env('FLATLAYER_SYNC_DEFAULT_PATTERN', '*.md'),
        'batch_size' => env('FLATLAYER_SYNC_BATCH_SIZE', 100),

        // Cache settings for sync state
        'cache_duration' => env('FLATLAYER_SYNC_CACHE_DURATION', 3600),

        // Logging settings
        'log_level' => env('FLATLAYER_SYNC_LOG_LEVEL', 'info'),
    ],
];
