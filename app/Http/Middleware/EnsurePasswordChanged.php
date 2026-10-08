<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public const MESSAGE = 'Change your temporary password to continue.';

    public const CODE = 'password_change_required';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('auth.user', 'auth.password', 'auth.logout')) {
            return $next($request);
        }

        return response()->json([
            'message' => self::MESSAGE,
            'code' => self::CODE,
        ], 403);
    }
}
