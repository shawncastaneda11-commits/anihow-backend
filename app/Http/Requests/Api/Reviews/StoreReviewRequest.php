<?php

namespace App\Http\Requests\Api\Reviews;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Review::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reservation_id' => ['required', 'integer', 'exists:reservations,id', Rule::unique('reviews', 'reservation_id')],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $reservation = Reservation::query()->find($this->integer('reservation_id'));

            if (! $reservation instanceof Reservation) {
                return;
            }

            if (! $reservation->isOwnedByBuyer($this->user())) {
                $validator->errors()->add('reservation_id', 'You can only review your own reservations.');

                return;
            }

            if ($reservation->status !== ReservationStatus::Completed) {
                $validator->errors()->add('reservation_id', 'You can only review a completed reservation.');
            }
        });
    }

    public function reservation(): Reservation
    {
        return Reservation::query()->findOrFail($this->integer('reservation_id'));
    }
}
