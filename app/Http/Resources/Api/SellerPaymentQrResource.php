<?php

namespace App\Http\Resources\Api;

use App\Models\SellerPaymentQr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SellerPaymentQr */
class SellerPaymentQrResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet' => $this->wallet->value,
            'wallet_label' => $this->wallet->label(),
            'account_name' => $this->account_name,
            'account_last4' => $this->account_last4,
            'image_url' => route('payment-qrs.image', $this->resource),
        ];
    }
}
