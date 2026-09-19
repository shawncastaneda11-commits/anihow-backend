<?php

namespace App\Http\Requests\Api\Orders;

use App\Enums\FulfillmentPreference;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * Fulfillment is a text arrangement between two people. There is no
     * courier, no fee, and no address validation here by design.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fulfillment_preference' => [
                'required',
                Rule::enum(FulfillmentPreference::class),
            ],
            'fulfillment_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
