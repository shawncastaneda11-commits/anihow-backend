<?php

namespace App\Providers;

use App\Support\ChatAttachmentLimiter;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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

        // Every password a person sets (sign-up, reset, change, CMS) needs
        // 8+ characters with upper and lower case, a number and a symbol.
        Password::defaults(fn (): Password => Password::min(8)->mixedCase()->numbers()->symbols());
        $this->configureBroadcasting();
        $this->configureCmsDates();
    }

    /**
     * CMS tables and detail pages show "Oct 23, 2026 4:39 PM": no seconds,
     * 12-hour time, so dates read naturally and keep columns narrow.
     */
    private function configureCmsDates(): void
    {
        Table::configureUsing(fn (Table $table): Table => $table->defaultDateTimeDisplayFormat('M j, Y g:i A'));
        Schema::configureUsing(fn (Schema $schema): Schema => $schema->defaultDateTimeDisplayFormat('M j, Y g:i A'));
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

            return Limit::perMinute((int) config('anihow.rate_limit_api', 240))
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
