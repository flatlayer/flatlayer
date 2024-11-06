<?php

return [
    'search' => [
        'openai' => [
            'embedding' => env('OPENAI_SEARCH_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
    ],

    'images' => [
        'use_signatures' => env('FLATLAYER_MEDIA_USE_SIGNATURES', false),
        'max_width' => env('FLATLAYER_MEDIA_MAX_WIDTH', 8192),
        'max_height' => env('FLATLAYER_MEDIA_MAX_HEIGHT', 8192),
    ],

    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],
];
