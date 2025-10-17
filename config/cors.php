<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration determines which cross-origin operations may execute
    | in web browsers. You may adjust these settings as needed.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'iot/*',
        'patient/*',
        'treatment/*'
    ],

    // Allow all HTTP methods (GET, POST, PUT, DELETE, etc.)
    'allowed_methods' => ['*'],

    // Allow requests only from your frontend and local dev
    'allowed_origins' => [
        'http://localhost:5173',                // local dev
        'http://127.0.0.1:5173',                // local dev alt
        'http://localhost:8000',                // Laravel local
        'http://127.0.0.1:8000',                // Laravel local alt
        'https://dialiease-4un0.onrender.com',  // ✅ your hosted frontend
    ],

    // If you need to allow dynamic subdomains, you can add regex here
    'allowed_origins_patterns' => [],

    // Allow all headers
    'allowed_headers' => ['*'],

    // Expose headers if needed (optional)
    'exposed_headers' => [],

    // How long the results of a preflight request can be cached
    'max_age' => 0,

    // If you're not using cookies or session auth across domains, keep this false
    'supports_credentials' => false,
];
