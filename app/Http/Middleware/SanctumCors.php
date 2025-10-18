<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanctumCors
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add CORS headers for all responses
        $response->headers->set('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept, Origin, X-XSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', 'X-CSRF-TOKEN, X-XSRF-TOKEN');

        return $response;
    }
}