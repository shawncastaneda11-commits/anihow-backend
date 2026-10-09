<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order !== null && $this->user()?->can('view', $order) === true
            && $order->isOwnedByBuyer($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference_number' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
            'qr_id' => ['required', 'integer'],
            'screenshot' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }
}
