<?php

use App\Models\Post;
use App\Models\Document;

return [
    'search' => [
        'embedding_model' => env('FLATLAYER_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'jina' => [
            'key' => env('JINA_API_KEY'),
            'rerank' => env('JINA_RERANK_MODEL', 'jina-reranker-v2-base-multilingual'),
            'embed' => env('JINA_EMBED_MODEL', 'jina-embeddings-v2-base-en'),
        ],
    ],
    'models' => [
        Post::class => [
            'path' => env('FLATLAYER_POST_PATH', ''),
            'source' => env('FLATLAYER_POST_SOURCE', '**/*.md'),
            'hook' => env('FLATLAYER_POST_HOOK', 'https://example.com/hook'),
        ],

        Document::class => [
            'path' => env('FLATLAYER_DOCUMENT_PATH', ''),
            'source' => env('FLATLAYER_DOCUMENT_SOURCE', '*.md'),
            'hook' => env('FLATLAYER_DOCUMENT_HOOK', 'https://example.com/hook'),
        ]
    ],
    'media' => [
        'use_signatures' => env('FLATLAYER_MEDIA_USE_SIGNATURES', false),
    ],
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ]
];
