<?php

namespace App\Http\Requests\Api\Orders;

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
        return [
            'amount_received' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }
}
