<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\MyAccount;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Home;
use App\Http\Middleware\AuthenticatePanel;
use App\Http\Middleware\RedirectIfMustChangePassword;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
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
            // Two-step sign-in by email code, off unless ICTD requires it for
            // administrators on System health. Turning it off there clears it
            // for everyone (SystemHealthPage::save).
            ->multiFactorAuthentication(
                [EmailAuthentication::make()],
                isRequired: fn (): bool => SystemSetting::requiresAdminTwoStep()
                    && (auth()->user() instanceof User && auth()->user()->isAdministrator()),
            )
            ->passwordReset()
            ->profile(MyAccount::class, isSimple: false)
            ->revealablePasswords()
            ->colors([
                'primary' => Color::hex('#1F5A3E'),
            ])
            // One menu for every office; each person sees only the items
            // their role can open.
            ->navigationGroups([
                'Overview',
                'People',
                'Farms',
                'Marketplace',
                'Moderation',
                'Content',
                'System',
                'Records',
            ])
            // Wide tables use the whole screen instead of scrolling sideways.
            ->maxContentWidth(Width::Full)
            // The mockup look: no breadcrumbs, flat menu groups. The theme
            // switch lives in the account menu.
            ->breadcrumbs(false)
            ->collapsibleNavigationGroups(false)
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
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => view('filament.login-side')->render(),
                scopes: Login::class,
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Home::class,
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
