<?php

namespace App\Providers;

use App\Mail\BrevoTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.trust_proxy_headers')) {
            TrustProxies::at('*');
            TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        }
        if ($this->app->isProduction() && str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }
        Mail::extend('brevo', fn (array $config) => new BrevoTransport((string) ($config['key'] ?? '')));
        RateLimiter::for('membership-applications', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('membership-weather', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('membership-confirmation', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('membership-resend', fn (Request $request) => Limit::perMinute(3)->by($request->user()->id.'|'.$request->ip()));
    }
}
