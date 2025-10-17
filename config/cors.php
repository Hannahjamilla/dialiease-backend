<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        '*', // ✅ Add this to catch all routes
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'https://dialiease-4un0.onrender.com',
        'https://dialiease-backend-1.onrender.com' // ✅ Add backend URL too
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Authorization', // ✅ Add this for Sanctum
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];