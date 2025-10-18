<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     */
    protected $middleware = [
        // Checks if app is under maintenance
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,

        // Validates the size of POST requests
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,

        // Trims whitespace from request data
        \App\Http\Middleware\TrimStrings::class,

        // Converts empty strings to null
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,

        // Handles CORS
        \Illuminate\Http\Middleware\HandleCors::class,
    ];

    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            // Encrypts cookies
            \App\Http\Middleware\EncryptCookies::class,

            // Adds queued cookies to the response
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            // Starts the session
            \Illuminate\Session\Middleware\StartSession::class,

            // Shares errors from the session to views
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            // Verifies CSRF token
            \App\Http\Middleware\VerifyCsrfToken::class,

            // Substitutes route bindings
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // Sanctum SPA authentication
            EnsureFrontendRequestsAreStateful::class,

            // Throttle API requests
            'throttle:api',

            // Substitute route bindings
            \Illuminate\Routing\Middleware\SubstituteBindings::class,

            // Handle CORS for API routes
            \Illuminate\Http\Middleware\HandleCors::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Example: Send reminders every 4 hours
        $schedule->command('reminders:send')->everyFourHours();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(_DIR_.'/../Console/Commands');

        require base_path('routes/console.php');
    }
}