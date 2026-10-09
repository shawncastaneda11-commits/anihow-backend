<?php

namespace App\Actions\Payments;

use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\ReservationStatus;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\SellerPaymentQr;
use App\Models\User;
use App\Support\ImageVariants;
use App\Support\InAppNotifier;
use App\Support\PaymentReference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitReservationPaymentProofAction
{
    public function __construct(
        private ImageVariants $images,
        private RecordPaymentEvent $events,
        private InAppNotifier $notifier,
        private PostPaymentChatNote $chat,
    ) {}

    public function handle(
        User $buyer,
        Reservation $reservation,
        string $referenceNumber,
        float|string $amount,
        int $qrId,
        ?UploadedFile $screenshot = null,
    ): PaymentProof {
        return DB::transaction(function () use ($buyer, $reservation, $referenceNumber, $amount, $qrId, $screenshot): PaymentProof {
            User::query()->whereKey($reservation->farmer_seller_id)->lockForUpdate()->first();

            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ((int) $reservation->buyer_id !== (int) $buyer->id) {
                abort(403);
            }

            if ($reservation->status !== ReservationStatus::Active || $reservation->payment_status !== OrderPaymentStatus::AwaitingPayment) {
                throw ValidationException::withMessages([
                    'reservation' => 'This reservation is not waiting for a payment.',
                ]);
            }

            if ($reservation->payment_due_at === null || now()->greaterThan($reservation->payment_due_at)) {
                throw ValidationException::withMessages([
                    'reservation' => 'The payment time has passed.',
                ]);
            }

            $reference = PaymentReference::normalize($referenceNumber);

            if (! preg_match('/^[A-Z0-9]{6,30}$/', $reference)) {
                throw ValidationException::withMessages([
                    'reference_number' => 'Enter 6 to 30 letters or digits.',
                ]);
            }

            $amountValue = round((float) $amount, 2);

            if ($amountValue <= 0 || $amountValue > 9999999.99) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount up to 9999999.99.',
                ]);
            }

            $snapshot = array_map('intval', $reservation->payment_qr_ids ?? []);

            if (! in_array($qrId, $snapshot, true)) {
                throw ValidationException::withMessages([
                    'qr_id' => 'Choose one of the QR codes for this reservation.',
                ]);
            }

            $qr = SellerPaymentQr::query()->withTrashed()->find($qrId);

            $duplicate = PaymentProof::query()
                ->where('farmer_seller_id', $reservation->farmer_seller_id)
                ->where('reference_number', $reference)
                ->whereIn('status', [PaymentProofStatus::Pending, PaymentProofStatus::Accepted])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'reference_number' => 'This reference number was already used.',
                ]);
            }

            $path = null;

            if ($screenshot !== null) {
                $path = $this->images->store(
                    $screenshot,
                    'payment-proofs/reservations/'.$reservation->id,
                    Storage::disk('local'),
                );
            }

            $proof = PaymentProof::query()->create([
                'order_id' => null,
                'reservation_id' => $reservation->id,
                'buyer_id' => $buyer->id,
                'farmer_seller_id' => $reservation->farmer_seller_id,
                'seller_payment_qr_id' => $qr?->id,
                'reference_number' => $reference,
                'amount' => $amountValue,
                'screenshot_path' => $path,
                'status' => PaymentProofStatus::Pending,
            ]);

            $reservation->payment_status = OrderPaymentStatus::PaymentSent;
            $reservation->save();

            $this->events->forReservation($reservation, 'proof_sent', $buyer, $reference);

            $reservation->loadMissing('farmerSeller');

            if ($reservation->farmerSeller !== null) {
                $this->notifier->send(
                    $reservation->farmerSeller,
                    NotificationType::PaymentProofSubmitted,
                    NotificationType::PaymentProofSubmitted->label(),
                    "{$buyer->name} sent payment proof for a reservation of {$reservation->listing_name}.",
                    $reservation,
                );
            }

            $wallet = $qr?->wallet?->label() ?? 'QR';
            $formatted = number_format($amountValue, 2, '.', '');

            $this->chat->forReservation(
                $buyer,
                $reservation,
                "Sent ₱{$formatted} via {$wallet}, ref {$reference} (reservation)",
            );

            return $proof;
        });
    }
}
