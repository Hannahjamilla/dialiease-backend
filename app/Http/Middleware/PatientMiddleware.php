<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PatientMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->user() && auth()->user()->userLevel === 'patient') {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }
}