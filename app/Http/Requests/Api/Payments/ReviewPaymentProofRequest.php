<?php

namespace App\Http\Requests\Api\Payments;

use App\Enums\PaymentRejectionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order !== null && ($this->user()?->can('advance', $order) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', Rule::enum(PaymentRejectionReason::class)],
            'note' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->input('decision') === 'reject'
                    && $this->input('reason') === PaymentRejectionReason::Other->value),
            ],
        ];
    }
}
