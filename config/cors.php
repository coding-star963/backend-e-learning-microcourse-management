<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    // The web and mobile apps authenticate with Bearer tokens, not cookies, so
    // cross-origin cookies are not allowed. ('*' origins + credentials would let
    // any website make logged-in requests.)
    'supports_credentials' => false,
];
