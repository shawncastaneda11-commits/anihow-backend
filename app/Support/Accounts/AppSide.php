<?php

namespace App\Support\Accounts;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Which side of the app a request comes from, for an account that both
 * sells and buys. The app's Selling / Buying switch sends it in the
 * X-AniHow-Side header ("selling" or "buying"). An account with one side
 * is always on that side; a seller who also buys is on the selling side
 * unless the app says buying, which is what an older app expects.
 */
final class AppSide
{
    public const HEADER = 'X-AniHow-Side';

    public static function isBuying(User $user, Request $request): bool
    {
        if (! $user->isBuyer()) {
            return false;
        }

        if (! $user->isFarmerSeller()) {
            return true;
        }

        return strtolower((string) ($request->header(self::HEADER) ?? $request->query('side'))) === 'buying';
    }
}
