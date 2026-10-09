<?php

namespace App\Models;

use App\Enums\PaymentWallet;
use Database\Factories\SellerPaymentQrFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'farmer_seller_id',
    'wallet',
    'account_name',
    'account_last4',
    'image_path',
])]
class SellerPaymentQr extends Model
{
    /** @use HasFactory<SellerPaymentQrFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'wallet' => PaymentWallet::class,
        ];
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id')->withTrashed();
    }
}
