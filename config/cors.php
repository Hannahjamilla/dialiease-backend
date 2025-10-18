<?php

return [
    'paths' => ['*', 'api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['https://dialiease-4un0.onrender.com'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-CSRF-TOKEN'],

    'max_age' => 0,

    'supports_credentials' => true,
];