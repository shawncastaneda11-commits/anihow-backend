<?php

namespace App\Actions\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Support\ImageVariants;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PurgeSettledProofFiles
{
    public function __construct(private ImageVariants $images) {}

    /**
     * Drop screenshot files 90 days after the order is finished. The reference
     * number and amount stay.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays(90);
        $deleted = 0;
        $disk = Storage::disk('local');

        $orderIds = Order::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($paid) use ($cutoff): void {
                    $paid->where('status', OrderStatus::Completed)
                        ->where('payment_status', OrderPaymentStatus::Paid)
                        ->where('completed_at', '<=', $cutoff);
                })->orWhere(function ($refunded) use ($cutoff): void {
                    $refunded->where('payment_status', OrderPaymentStatus::Refunded)
                        ->where('cancelled_at', '<=', $cutoff);
                })->orWhere(function ($cancelled) use ($cutoff): void {
                    $cancelled->where('status', OrderStatus::Cancelled)
                        ->where('cancelled_at', '<=', $cutoff)
                        ->whereDoesntHave('proofs', function ($proofs): void {
                            $proofs->where('status', PaymentProofStatus::Accepted);
                        });
                });
            })
            ->pluck('id');

        $deleted += $this->deleteScreenshots(
            PaymentProof::query()->whereIn('order_id', $orderIds),
            $disk,
        );

        $reservationIds = Reservation::query()
            ->where('status', ReservationStatus::Cancelled)
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($refunded) use ($cutoff): void {
                    $refunded->where('payment_status', OrderPaymentStatus::Refunded)
                        ->where('refunded_at', '<=', $cutoff);
                })->orWhere(function ($settled) use ($cutoff): void {
                    $settled->where('cancelled_at', '<=', $cutoff)
                        ->whereDoesntHave('proofs', function ($proofs): void {
                            $proofs->where('status', PaymentProofStatus::Accepted);
                        });
                });
            })
            ->pluck('id');

        $deleted += $this->deleteScreenshots(
            PaymentProof::query()->whereIn('reservation_id', $reservationIds),
            $disk,
        );

        return $deleted;
    }

    /**
     * @param  Builder<PaymentProof>  $query
     */
    private function deleteScreenshots(Builder $query, Filesystem $disk): int
    {
        $deleted = 0;

        $query
            ->whereNotNull('screenshot_path')
            ->whereNull('screenshot_deleted_at')
            ->orderBy('id')
            ->each(function (PaymentProof $proof) use ($disk, &$deleted): void {
                $this->images->delete($proof->screenshot_path, $disk);
                $proof->forceFill(['screenshot_deleted_at' => now()])->save();
                $deleted++;
            });

        return $deleted;
    }
}
