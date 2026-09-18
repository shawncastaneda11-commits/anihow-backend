<?php

namespace App\Http\Requests\Api\Reservations;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        if (! $reservation instanceof Reservation) {
            return false;
        }

        $user = $this->user();

        if ($user?->isBuyer()) {
            return $user->can('cancelAsBuyer', $reservation);
        }

        if ($user?->isFarmerSeller()) {
            return $user->can('manageAsFarmer', $reservation);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                $this->user()?->isFarmerSeller() ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
