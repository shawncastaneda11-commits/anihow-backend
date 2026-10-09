<?php

namespace App\Actions\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProofStatus;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Support\ImageVariants;
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

        PaymentProof::query()
            ->whereIn('order_id', $orderIds)
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
