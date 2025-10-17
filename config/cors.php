return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register',
        'user',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://dialiease-4un0.onrender.com', // your frontend URL on Render
        'http://localhost:5173',               // for local development
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
