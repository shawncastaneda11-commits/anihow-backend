<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Models\Listing;
use App\Support\Pricing\PriceGuardResolver;
use App\Support\Pricing\UnitConverter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Listing::class) ?? false;
    }

    /**
     * The seller chooses the unit. A crop with a floor price or a maximum
     * discount only accepts a unit that converts into the crop type's unit.
     * Analytics sum each family in its base unit, so grams and kilograms
     * still add up.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crop_type_id' => ['required', 'integer', 'exists:crop_types,id'],
            'unit' => ['required', Rule::enum(ListingUnit::class)],
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
     *
     * The floor is the seller's farm's effective floor: the system floor,
     * raised by the farm where the farm has raised it.
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

                $seller = $this->user();

                if ($seller !== null && ! $seller->mayUseCropType($cropType->id)) {
                    $validator->errors()->add(
                        'crop_type_id',
                        'This crop type is not on your list.',
                    );

                    return;
                }

                $converter = app(UnitConverter::class);
                $unit = ListingUnit::from($this->validated('unit'));

                if (! $converter->accepts($cropType, $unit, $seller?->farm_id)) {
                    $validator->errors()->add(
                        'unit',
                        $converter->refusalMessage($cropType, $seller?->farm_id),
                    );

                    return;
                }

                $guard = app(PriceGuardResolver::class)->forFarmId($seller?->farm_id, $cropType);
                $price = $converter->priceIn($unit, $cropType->unit_of_measure, $this->validated('price_per_unit'));

                if (! $guard->allowsPrice($price)) {
                    $floor = number_format($guard->floor, 2, '.', '');
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
            'unit' => $this->validated('unit'),
            'title' => $this->validated('title'),
            'description' => $this->validated('description'),
            'price_per_unit' => $this->validated('price_per_unit'),
            'quantity_available' => $this->validated('quantity_available'),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
