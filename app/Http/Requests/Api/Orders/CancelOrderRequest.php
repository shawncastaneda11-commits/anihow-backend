<?php

namespace App\Http\Requests\Api\Orders;

use App\Enums\CancellationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('order')) ?? false;
    }

    /**
     * A no-show is a cancellation reason, not a separate status. Sellers pick
     * from their own set; a buyer cancelling always records buyer_cancelled,
     * so the reason field is ignored on the buyer route.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'sometimes',
                Rule::enum(CancellationReason::class),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('reason')) {
            $this->merge(['reason' => CancellationReason::Other->value]);
        }
    }
}
