<?php

namespace App\Actions\Privacy;

use App\Enums\UserStatus;
use App\Models\TawadRule;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnonymizeUserAction
{
    /**
     * Irreversible mutations only. The caller owns the transaction and the
     * completion mail, so a failed SMTP cannot roll this back.
     */
    public function handle(User $user): void
    {
        if ($user->hasOpenMarketplaceOrders()) {
            throw ValidationException::withMessages([
                'status' => 'This account still has open orders and cannot be anonymised.',
            ]);
        }

        $user->listings()->update(['is_active' => false]);

        TawadRule::query()
            ->where('is_active', true)
            ->whereIn('listing_id', $user->listings()->select('id'))
            ->update([
                'is_active' => false,
                'ended_at' => now(),
            ]);

        $user->cartItems()->delete();
        $user->favorites()->delete();
        $user->shopFavorites()->delete();
        $user->inAppNotifications()->delete();
        $user->tokens()->delete();

        $user->forceFill([
            'name' => 'Deleted user',
            'email' => "deleted-{$user->id}@anihow.invalid",
            'phone' => null,
            'location' => null,
            'shop_name' => null,
            'bio' => null,
            'contact' => null,
            'password' => Str::password(32),
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'Account deletion completed',
            'email_verified_at' => null,
            'remember_token' => Str::random(40),
        ])->save();

        $user->delete();
    }
}
