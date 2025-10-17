<?php

return [

    'paths' => [
        'api/*',
        'iot/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'patient/*',
        'treatment/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'https://dialiease-4un0.onrender.com',     // ✅ Frontend (Render)
        'https://dialiease-backend-1.onrender.com' // ✅ Backend (Render)
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'X-CSRF-TOKEN',
        'X-Device-ID',
        'X-Requested-Device',
    ],

    'exposed_headers' => [
        'Authorization',
        'X-CSRF-TOKEN',
        'X-Device-Status',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 86400, // 24 hours

    'supports_credentials' => true, // ✅ only one instance of this
];
