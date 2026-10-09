<?php

namespace App\Actions\Privacy;

use App\Actions\Reservations\CancelReservation;
use App\Enums\ReservationCancellationReason;
use App\Enums\UserStatus;
use App\Models\PaymentProof;
use App\Models\SellerPaymentQr;
use App\Models\StallMessage;
use App\Models\TawadRule;
use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Support\Facades\Storage;
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

        $cancelReservation = app(CancelReservation::class);
        $cancelReservation->forBuyer($user, ReservationCancellationReason::AccountClosed);
        $cancelReservation->forSeller($user, ReservationCancellationReason::AccountClosed);

        $user->listings()->update(['is_active' => false]);

        TawadRule::query()
            ->where('is_active', true)
            ->whereIn('listing_id', $user->listings()->select('id'))
            ->update([
                'is_active' => false,
                'ended_at' => now(),
            ]);

        $images = app(ImageVariants::class);
        $disk = Storage::disk('local');

        SellerPaymentQr::query()
            ->withTrashed()
            ->where('farmer_seller_id', $user->id)
            ->orderBy('id')
            ->each(function (SellerPaymentQr $qr) use ($images, $disk): void {
                $images->delete($qr->image_path, $disk);
                $qr->forceFill(['image_path' => ''])->save();
            });

        PaymentProof::query()
            ->where(function ($query) use ($user): void {
                $query->where('buyer_id', $user->id)
                    ->orWhere('farmer_seller_id', $user->id);
            })
            ->whereNotNull('screenshot_path')
            ->orderBy('id')
            ->each(function (PaymentProof $proof) use ($images, $disk): void {
                $images->delete($proof->screenshot_path, $disk);
                $proof->forceFill([
                    'screenshot_path' => null,
                    'screenshot_deleted_at' => now(),
                ])->save();
            });

        StallMessage::query()
            ->where('user_id', $user->id)
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->each(function (StallMessage $message): void {
                $message->deleteStoredAttachment();
                $message->forceFill([
                    'attachment_path' => null,
                    'attachment_mime' => null,
                    'attachment_size' => null,
                    'attachment_name' => null,
                ])->save();
            });

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
            'avatar_path' => null,
            'cover_photo_path' => null,
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
