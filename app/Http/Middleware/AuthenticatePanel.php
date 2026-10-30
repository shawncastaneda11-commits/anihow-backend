<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament's panel check, plus a clean sign-out for an account suspended while
 * signed in. Without it the parent answers a suspended account with a bare
 * 403 and leaves the session in place.
 */
class AuthenticatePanel extends Authenticate
{
    /**
     * @param  Request  $request
     * @param  string  ...$guards
     */
    public function handle($request, Closure $next, ...$guards): Response
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && ! $user->status->canAuthenticate()) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Notification::make()
                ->title(EnsureAccountIsActive::MESSAGE)
                ->danger()
                ->persistent()
                ->send();

            return redirect()->to(Filament::getLoginUrl());
        }

        return parent::handle($request, $next, ...$guards);
    }
}
