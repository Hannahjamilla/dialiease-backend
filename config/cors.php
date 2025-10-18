<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'user',
        'forgot-password',
        'reset-password',
        'register',
        'email/verify/*',
        'email/resend',
        'oauth/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://dialiease-4un0.onrender.com',
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:8000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Credentials',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];