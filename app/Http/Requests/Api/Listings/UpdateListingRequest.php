<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\GrowingMethod;
use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Models\Listing;
use App\Support\Pricing\PriceGuardResolver;
use App\Support\Pricing\UnitConverter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'unit' => ['sometimes', 'required', Rule::enum(ListingUnit::class)],
            'title' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price_per_unit' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999.9999'],
            'quantity_available' => ['sometimes', 'numeric', 'min:0', 'max:99999.99'],
            'is_active' => ['sometimes', 'boolean'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'available_until' => ['sometimes', 'nullable', 'date'],
            'harvested_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'growing_method' => ['sometimes', 'nullable', Rule::enum(GrowingMethod::class)],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * The resulting price is checked against the listing's farm's effective
     * floor for the resulting crop type, whichever of the two the request
     * changes. A price left untouched is still rechecked, so a seller cannot
     * edit the title of a stranded listing and have it pass silently.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $listing = $this->listing();

                StoreListingRequest::assertAvailabilityWindow(
                    $validator,
                    $this->exists('available_from') ? $this->input('available_from') : $listing->available_from,
                    $this->exists('available_until') ? $this->input('available_until') : $listing->available_until,
                );

                if ($this->exists('growing_method')) {
                    StoreListingRequest::assertGrowingMethod(
                        $validator,
                        $this->input('growing_method'),
                        $listing->farm,
                    );
                }

                $cropType = $this->has('crop_type_id')
                    ? CropType::find($this->validated('crop_type_id'))
                    : $listing->cropType;

                if ($cropType === null) {
                    return;
                }

                $seller = $this->user();

                if (
                    $seller !== null
                    && $this->has('crop_type_id')
                    && (int) $cropType->id !== (int) $listing->crop_type_id
                    && ! $seller->mayUseCropType($cropType->id)
                ) {
                    $validator->errors()->add(
                        'crop_type_id',
                        'This crop type is not on your list.',
                    );

                    return;
                }

                $unit = $this->has('unit')
                    ? ListingUnit::from($this->validated('unit'))
                    : ($listing->unit ?? $cropType->unit_of_measure);

                $converter = app(UnitConverter::class);

                if (! $converter->accepts($cropType, $unit, $listing->farm_id)) {
                    $validator->errors()->add(
                        'unit',
                        $converter->refusalMessage($cropType, $listing->farm_id),
                    );

                    return;
                }

                $price = $this->has('price_per_unit')
                    ? (float) $this->validated('price_per_unit')
                    : (float) $listing->price_per_unit;

                $guard = app(PriceGuardResolver::class)->forFarmId($listing->farm_id, $cropType);
                $price = $converter->guardPrice($unit, $cropType->unit_of_measure, $price);

                if ($price === null) {
                    if ($converter->isGuarded($cropType, $listing->farm_id)) {
                        $validator->errors()->add(
                            'unit',
                            $converter->refusalMessage($cropType, $listing->farm_id),
                        );
                    }

                    return;
                }

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
        return collect($this->validated())
            ->only([
                'crop_type_id',
                'unit',
                'title',
                'description',
                'price_per_unit',
                'quantity_available',
                'is_active',
                'available_from',
                'available_until',
                'harvested_on',
                'growing_method',
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
