<?php

use App\Models\Post;

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
            'source' => '/foo/bar/*.md',
            'hook' => 'https://example.com/hook',
        ]
    ]
];
