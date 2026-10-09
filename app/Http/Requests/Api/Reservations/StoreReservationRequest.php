<?php

namespace App\Http\Requests\Api\Reservations;

use App\Enums\FulfillmentPreference;
use App\Http\Requests\Api\Cart\StoreCartItemRequest;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Reservation::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('fulfillment_preference')) {
            $this->merge([
                'fulfillment_preference' => FulfillmentPreference::BuyerPickup->value,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'quantity' => StoreCartItemRequest::quantityRules(),
            'fulfillment_preference' => ['required', Rule::enum(FulfillmentPreference::class)],
            'fulfillment_note' => ['nullable', 'string', 'max:1000'],
            'payment_flow' => ['nullable', 'string'],
        ];
    }
}
