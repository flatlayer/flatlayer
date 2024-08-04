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
            'source' => '/foo/bar/*.md',
            'hook' => 'https://example.com/hook',
        ],

        Document::class => [
            '/Users/gpriday/Sites/pixashot-website/static/content/docs/*.md',
            'https://example.com/hook',
        ]
    ]
];
