<?php

namespace App\Http\Middleware;

use Closure;

class SanctumCors
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Add CORS headers
        $response->headers->set('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', 'X-CSRF-TOKEN');

        return $response;
    }
}