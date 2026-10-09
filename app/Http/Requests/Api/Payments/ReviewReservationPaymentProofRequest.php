<?php

namespace App\Http\Requests\Api\Payments;

use App\Enums\PaymentRejectionReason;
use App\Enums\Permission;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewReservationPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');
        $user = $this->user();

        return $reservation instanceof Reservation
            && $user !== null
            && (int) $reservation->farmer_seller_id === (int) $user->id
            && $user->can(Permission::ManageOwnListings->value);
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
