<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\GrowingMethod;
use App\Enums\ListingUnit;
use App\Models\Listing;
use App\Support\Pricing\PriceGuardResolver;
use App\Support\Pricing\UnitConverter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    public const CROP_LOCKED_MESSAGE = "The crop type can't be changed. Create a new listing for a different crop.";

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
            // The crop type is fixed once a listing exists: its floor price,
            // tawad limit, orders and sales reports all hang off it.
            'crop_type_id' => ['sometimes', 'integer', Rule::in([(int) $this->listing()->crop_type_id])],
            'unit' => ['sometimes', 'required', Rule::enum(ListingUnit::class)],
            'title' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price_per_unit' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999.9999'],
            'quantity_available' => ['sometimes', 'numeric', 'min:0', 'max:99999.99'],
            'min_order_quantity' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999.99'],
            'order_step' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999.99'],
            'is_active' => ['sometimes', 'boolean'],
            'confirm_cancel_reservations' => ['sometimes', 'boolean'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'available_until' => ['sometimes', 'nullable', 'date'],
            'harvested_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'growing_method' => ['sometimes', 'nullable', Rule::enum(GrowingMethod::class)],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'crop_type_id.in' => self::CROP_LOCKED_MESSAGE,
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

                $cropType = $listing->cropType;

                if ($cropType === null) {
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

                $min = $this->exists('min_order_quantity')
                    ? (float) $this->input('min_order_quantity')
                    : (float) $listing->min_order_quantity;
                $step = $this->exists('order_step')
                    ? (float) $this->input('order_step')
                    : (float) $listing->order_step;

                Listing::addOrderRuleErrors($validator, $unit, $min, $step);

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
                'min_order_quantity',
                'order_step',
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
