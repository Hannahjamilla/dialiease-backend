<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://dialiease-4un0.onrender.com', // frontend
        'https://dialiease-backend-1.onrender.com', // backend itself
        'http://localhost:5173', // dev
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Authorization',
        'X-CSRF-TOKEN',
        'X-Requested-With',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
