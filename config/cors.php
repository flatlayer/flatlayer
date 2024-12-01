<?php

return [
    // Allow CORS for all routes instead of just API routes
    'paths' => ['*'],

    // Allow all HTTP methods (GET, POST, etc)
    'allowed_methods' => ['*'],

    // Allow requests from any domain/origin
    'allowed_origins' => ['*'],

    // No specific origin patterns needed since we allow all origins
    'allowed_origins_patterns' => [],

    // Allow all headers in requests
    'allowed_headers' => ['*'],

    // No additional headers exposed to browsers
    'exposed_headers' => [],

    // Browser can cache preflight request for 0 seconds (disabled)
    'max_age' => 0,

    // Don't send credentials (cookies, HTTP auth) cross-origin
    'supports_credentials' => false,
];
