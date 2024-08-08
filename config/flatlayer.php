<?php

return [
    'search' => [
        'jina' => [
            'key' => env('JINA_API_KEY'),
            'rerank' => env('JINA_RERANK_MODEL', 'jina-reranker-v2-base-multilingual'),
            'embed' => env('JINA_EMBED_MODEL', 'jina-embeddings-v2-base-en'),
        ],
    ],

    'images' => [
        'use_signatures' => env('FLATLAYER_MEDIA_USE_SIGNATURES', false),
    ],

    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],
];
