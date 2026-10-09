<?php

namespace App\Actions\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\SellerPaymentQr;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteSellerPaymentQrAction
{
    public function handle(User $seller, SellerPaymentQr $qr): void
    {
        if ($qr->farmer_seller_id !== $seller->id) {
            abort(403);
        }

        $others = $seller->paymentQrs()->whereKeyNot($qr->id)->count();

        if ($others === 0) {
            $trackedReservations = Reservation::query()
                ->where('farmer_seller_id', $seller->id)
                ->where('status', ReservationStatus::Active)
                ->whereNotNull('payment_status')
                ->where('payment_status', '!=', OrderPaymentStatus::NotTracked->value)
                ->exists();

            if ($trackedReservations) {
                throw ValidationException::withMessages([
                    'qr' => 'Finish or cancel your reservations first.',
                ]);
            }

            $waiting = Order::query()
                ->where('farmer_seller_id', $seller->id)
                ->whereIn('payment_status', [
                    OrderPaymentStatus::AwaitingPayment,
                    OrderPaymentStatus::PaymentSent,
                ])
                ->exists();

            if ($waiting) {
                throw ValidationException::withMessages([
                    'qr' => "You can't delete your last QR while a buyer is still paying.",
                ]);
            }

            $qr->delete();
            $seller->forceFill(['accepts_online_payment' => false])->save();

            return;
        }

        $qr->delete();
    }
}
