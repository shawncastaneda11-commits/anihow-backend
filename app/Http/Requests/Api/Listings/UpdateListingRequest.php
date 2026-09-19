<?php

namespace App\Http\Requests\Api\Listings;

use App\Models\CropType;
use App\Models\Listing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('listing')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crop_type_id' => ['sometimes', 'integer', 'exists:crop_types,id'],
            'title' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price_per_unit' => ['sometimes', 'numeric', 'gt:0', 'max:99999.99'],
            'quantity_available' => ['sometimes', 'numeric', 'min:0', 'max:99999.99'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $listing = $this->listing();

                $cropType = $this->has('crop_type_id')
                    ? CropType::find($this->validated('crop_type_id'))
                    : $listing->cropType;

                $price = $this->has('price_per_unit')
                    ? (float) $this->validated('price_per_unit')
                    : (float) $listing->price_per_unit;

                if ($cropType === null || $cropType->allowsPrice($price)) {
                    return;
                }

                $floor = number_format((float) $cropType->floor_price, 2, '.', '');
                $validator->errors()->add(
                    'price_per_unit',
                    "The floor price for {$cropType->name} is PHP {$floor} per {$cropType->unit_of_measure->value}.",
                );
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listingAttributes(): array
    {
        return collect($this->validated())
            ->only([
                'crop_type_id',
                'title',
                'description',
                'price_per_unit',
                'quantity_available',
                'is_active',
            ])
            ->all();
    }

    private function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing->loadMissing('cropType')
            : Listing::with('cropType')->findOrFail($listing);
    }
}
