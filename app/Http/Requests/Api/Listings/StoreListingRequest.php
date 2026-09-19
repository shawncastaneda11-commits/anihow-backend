<?php

namespace App\Http\Requests\Api\Listings;

use App\Models\CropType;
use App\Models\Listing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Listing::class) ?? false;
    }

    /**
     * No unit field. Unit of measure belongs to the crop type, or two sellers
     * list the same crop in different units and units-sold means nothing.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crop_type_id' => ['required', 'integer', 'exists:crop_types,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_per_unit' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            'quantity_available' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * Floor price is checked here and again at checkout. This one is for the
     * seller's benefit; the checkout one is the guarantee.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $cropType = CropType::find($this->validated('crop_type_id'));

                if ($cropType === null) {
                    return;
                }

                if (! $cropType->allowsPrice((float) $this->validated('price_per_unit'))) {
                    $floor = number_format((float) $cropType->floor_price, 2, '.', '');
                    $validator->errors()->add(
                        'price_per_unit',
                        "The floor price for {$cropType->name} is PHP {$floor} per {$cropType->unit_of_measure->value}.",
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listingAttributes(): array
    {
        return [
            'crop_type_id' => $this->validated('crop_type_id'),
            'title' => $this->validated('title'),
            'description' => $this->validated('description'),
            'price_per_unit' => $this->validated('price_per_unit'),
            'quantity_available' => $this->validated('quantity_available'),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
