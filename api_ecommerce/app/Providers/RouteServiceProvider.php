<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
        
        // ChatBot: cada mensaje dispara varias llamadas a Gemini (costo real).
        RateLimiter::for('chat', function (Request $request) {
            $quien = auth('api')->id() ?: $request->ip();
            $responder = fn () => response()->json([
                'message' => 'Enviaste muchos mensajes seguidos. Esperá un momento e intentá de nuevo.',
            ], 429);

            return [
                Limit::perMinute(15)->by("chat:min:{$quien}")->response($responder),
                Limit::perDay(200)->by("chat:dia:{$request->ip()}")->response($responder),
            ];
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
