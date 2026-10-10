<?php

namespace App\Providers;

use App\Support\ChatAttachmentLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        if ((bool) config('anihow.force_https')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->configureBroadcasting();
    }

    private function configureBroadcasting(): void
    {
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('public', function (Request $request) {
            if (app()->runningUnitTests()) {
                return Limit::none();
            }

            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            if (app()->runningUnitTests()) {
                return Limit::none();
            }

            return Limit::perMinute((int) config('anihow.rate_limit_api', 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            if (app()->runningUnitTests()) {
                return Limit::none();
            }

            $email = Str::lower((string) $request->input('email', ''));

            return Limit::perMinute((int) config('anihow.rate_limit_auth', 5))
                ->by($email.'|'.$request->ip());
        });

        RateLimiter::for('data-export', function (Request $request) {
            if (app()->runningUnitTests()) {
                return Limit::none();
            }

            return Limit::perHour(3)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('reports', function (Request $request) {
            if (app()->runningUnitTests()) {
                return Limit::none();
            }

            return Limit::perHour(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('chat-attachments', function (Request $request) {
            if (app()->runningUnitTests()) {
                return Limit::none();
            }

            return app(ChatAttachmentLimiter::class)->limit($request);
        });
    }
}
