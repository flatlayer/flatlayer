<?php

return [
    'search' => [
        'embedding_model' => 'text-embedding-3-small',
        'jina' => [
            'key' => env('JINA_API_KEY'),
            'model' => 'jina-reranker-v2-base-multilingual',
        ],
    ]
];
