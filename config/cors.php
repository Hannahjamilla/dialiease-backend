<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'patient/*',
        'treatment/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',                   // local dev
        'http://127.0.0.1:5173',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'https://dialiease-4un0.onrender.com',     // frontend
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [
        'Authorization',
        'X-CSRF-TOKEN',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
