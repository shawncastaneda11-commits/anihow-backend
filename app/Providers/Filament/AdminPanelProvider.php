<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\AuthenticatePanel;
use App\Http\Middleware\RedirectIfMustChangePassword;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('AniHow')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('auto')
            ->favicon(asset('images/anihow-mark.png'))
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::Light)
            ->themeSwitcher(true)
            ->login(Login::class)
            ->passwordReset()
            ->revealablePasswords()
            ->colors([
                'primary' => Color::Green,
            ])
            // Wide tables use the whole screen instead of scrolling sideways.
            ->maxContentWidth(Width::Full)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('@include(\'filament.brand-styles\')'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => request()->routeIs('filament.admin.resources.farms.edit')
                    ? view('filament.farms.leaflet-assets')->render()
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render('@include(\'filament.topbar-theme-switcher\')'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->userMenuItems([
                'account' => Action::make('account')
                    ->label('My account')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn (): string => UserResource::getUrl('edit', ['record' => auth()->id()])),
            ])
            /*
             * Persistent, so these also run on every in-page action and a
             * suspended account is signed out on its next click.
             */
            ->authMiddleware([
                AuthenticatePanel::class,
            ], isPersistent: true)
            ->authMiddleware([
                RedirectIfMustChangePassword::class,
            ]);
    }
}
