<?php

namespace App\Http\Requests\Api\Orders;

use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class CompleteOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('advance', $this->route('order')) ?? false;
    }

    /**
     * The cash counted at handover. Recorded, never processed. It is allowed
     * to differ from the order total: people round, and the record should say
     * what actually changed hands.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $order = $this->route('order');
        $proofCoversIt = $order instanceof Order
            && $order->isPaymentTracked()
            && $order->payment_status === OrderPaymentStatus::Paid;

        return [
            'amount_received' => [
                $proofCoversIt ? 'sometimes' : 'required',
                'nullable',
                'numeric',
                'min:0',
                'max:9999999.99',
            ],
        ];
    }
}
