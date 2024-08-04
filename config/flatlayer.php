<?php

use App\Models\Post;
use App\Models\Document;

return [
    'search' => [
        'embedding_model' => 'text-embedding-3-small',
        'jina' => [
            'key' => env('JINA_API_KEY'),
            'model' => 'jina-reranker-v2-base-multilingual',
        ],
    ],
    'models' => [
        Post::class => [
            'path' => '',
            'source' => '*.md',
            'hook' => 'https://example.com/hook',
        ],

        Document::class => [
            'path' => '/Users/gpriday/Sites/pixashot-website/static/content/docs/',
            'source' => '*.md',
            'hook' => 'https://example.com/hook',
        ]
    ],
    'media' => [
        'use_signatures' => env('FLATLAYER_MEDIA_USE_SIGNATURES', false),
    ],
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ]
];
