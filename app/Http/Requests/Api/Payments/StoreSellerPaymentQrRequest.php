<?php

namespace App\Http\Requests\Api\Payments;

use App\Enums\PaymentWallet;
use App\Models\SellerPaymentQr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellerPaymentQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SellerPaymentQr::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'wallet' => ['required', Rule::enum(PaymentWallet::class)],
            'account_name' => ['required', 'string', 'max:80'],
            'account_last4' => ['required', 'digits:4'],
            'image' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }
}
