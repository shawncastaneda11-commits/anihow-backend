<?php

namespace App\Models;

use App\Enums\PaymentProofStatus;
use App\Enums\PaymentRejectionReason;
use Database\Factories\PaymentProofFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'buyer_id',
    'farmer_seller_id',
    'seller_payment_qr_id',
    'reference_number',
    'amount',
    'screenshot_path',
    'screenshot_deleted_at',
    'status',
    'rejection_reason',
    'rejection_note',
    'reviewed_by',
    'reviewed_at',
    'reminder_12h_at',
    'reminder_24h_at',
])]
class PaymentProof extends Model
{
    /** @use HasFactory<PaymentProofFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentProofStatus::class,
            'rejection_reason' => PaymentRejectionReason::class,
            'screenshot_deleted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'reminder_12h_at' => 'datetime',
            'reminder_24h_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id')->withTrashed();
    }

    public function paymentQr(): BelongsTo
    {
        return $this->belongsTo(SellerPaymentQr::class, 'seller_payment_qr_id')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function hasScreenshot(): bool
    {
        return filled($this->screenshot_path) && $this->screenshot_deleted_at === null;
    }
}
