<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops checkout and reservations for buyers who must verify their ID and
 * have not been approved yet. Browsing, the cart and chat stay open.
 */
class EnsureIdVerified
{
    public const MESSAGE = 'Verify your ID to place orders.';

    public const CODE = 'id_verification_required';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->mustVerifyIdToOrder()) {
            return $next($request);
        }

        return response()->json([
            'message' => self::MESSAGE,
            'code' => self::CODE,
        ], 403);
    }
}
