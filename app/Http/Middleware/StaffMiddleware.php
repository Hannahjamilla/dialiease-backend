<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class StaffMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->user() && in_array(auth()->user()->userLevel, ['staff', 'nurse'])) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }
}