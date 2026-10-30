<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspension takes effect on the app's next request, not only at the next
 * sign-in: the app signs out on any 401. The panel does the same through
 * AuthenticatePanel.
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'This account is suspended. Contact the AniHow administrator.';

    public const CODE = 'account_suspended';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->status->canAuthenticate()) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => self::MESSAGE,
            'code' => self::CODE,
        ], 401);
    }
}
