<?php

return [
    'paths' => [
        'api/*',
        'iot/*',  // Explicitly include IoT endpoints
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'patient/*',
        'treatment/*'  // Add if you have specific treatment routes
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',  // Your Vite/React dev server
        'http://localhost:8000',  // Laravel dev server
        'http://127.0.0.1:5173',  // Alternative localhost
        'http://127.0.0.1:8000',
        'https://dialiase.vercel.app',            // React (Vercel) production URL
        'https://dialiase-api.onrender.com',
    ],
    'supports_credentials' => false, // set true only if you use cookies/sessions across domains

    'allowed_origins_patterns' => [
        // For dynamic subdomains if needed
        // '^https?://([a-z0-9-]+\.)?yourdomain\.com$',
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'X-CSRF-TOKEN',
        'X-Device-ID',  // For IoT device identification
        'X-Requested-Device'  // Custom headers your IoT might use
    ],

    'exposed_headers' => [
        'Authorization',
        'X-CSRF-TOKEN',
        'X-Device-Status',  // For IoT status responses
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining'
    ],

    'max_age' => 60 * 60 * 24, // 24 hours

    'supports_credentials' => true,  // Important for sessions/cookies
];