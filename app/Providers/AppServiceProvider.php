<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request): Limit {
            return Limit::perMinutes(15, 5)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => redirect()
                    ->route('login')
                    ->withErrors(['email' => 'Terlalu banyak percobaan masuk. Coba lagi 15 menit lagi.'])
                    ->withHeaders($headers));
        });
    }
}