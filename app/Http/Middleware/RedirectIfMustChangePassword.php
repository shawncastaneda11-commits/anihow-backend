<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ChangePassword;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfMustChangePassword
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->is('admin/change-password', 'admin/logout')) {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.auth.logout', ChangePassword::getRouteName())) {
            return $next($request);
        }

        return redirect()->to(ChangePassword::getUrl());
    }
}
