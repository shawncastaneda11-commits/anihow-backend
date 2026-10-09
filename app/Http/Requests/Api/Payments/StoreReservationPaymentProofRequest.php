<?php

namespace App\Http\Requests\Api\Payments;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        return $reservation instanceof Reservation
            && $this->user() !== null
            && (int) $reservation->buyer_id === (int) $this->user()->id;
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
