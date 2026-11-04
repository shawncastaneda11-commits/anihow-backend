<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\TawadType;
use App\Models\Listing;
use App\Models\TawadRule;
use App\Support\Pricing\PriceGuardResolver;
use App\Support\Pricing\UnitConverter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTawadRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TawadRule::class) ?? false;
    }

    /**
     * Peso amounts only. There is no percentage input anywhere in this system.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TawadType::class)],
            'discount_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999.9999'],
            'min_quantity' => [
                'nullable',
                'required_if:type,'.TawadType::MinimumQuantity->value,
                'numeric',
                'gt:0',
            ],
        ];
    }

    /**
     * Ceiling and floor are checked here as well as at checkout, through the
     * same TawadRule::guardProblem(). This one gives the seller an immediate
     * error; the checkout one is the guarantee. The tawad is judged per unit:
     * spread over the quantity it applies to, then compared in the crop
     * type's unit.
     *
     * Both are the listing's farm's effective values: the system ceiling,
     * lowered by the farm where it has lowered it, and the system floor,
     * raised by the farm where it has raised it.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $listing = $this->listing();
                $cropType = $listing->cropType;
                $guard = app(PriceGuardResolver::class)->forFarmId($listing->farm_id, $cropType);
                $unit = $cropType->unit_of_measure->value;

                $rule = new TawadRule([
                    'type' => TawadType::from($this->validated('type')),
                    'discount_amount' => (float) $this->validated('discount_amount'),
                    'min_quantity' => $this->validated('min_quantity'),
                ]);

                $problem = $rule->guardProblem($listing, $guard);

                if ($problem === 'unit') {
                    $validator->errors()->add(
                        'discount_amount',
                        app(UnitConverter::class)->refusalMessage($cropType, $listing->farm_id),
                    );

                    return;
                }

                if ($problem === 'ceiling') {
                    $perUnit = number_format((float) $rule->perUnitInCropUnit($listing), 2, '.', '');
                    $validator->errors()->add(
                        'discount_amount',
                        $guard->hasCeiling() && (float) $guard->ceiling > 0
                            ? "The maximum tawad for {$cropType->name} is PHP {$guard->ceilingText()} per {$unit}. "
                                ."This tawad works out to PHP {$perUnit} per {$unit}."
                            : "Tawad is turned off for {$cropType->name}.",
                    );

                    return;
                }

                if ($problem === 'floor') {
                    $floor = number_format($guard->floor, 2, '.', '');
                    $validator->errors()->add(
                        'discount_amount',
                        "This tawad would bring the price below the floor of PHP {$floor} per {$unit}.",
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleAttributes(): array
    {
        return [
            'type' => TawadType::from($this->validated('type')),
            'discount_amount' => $this->validated('discount_amount'),
            'min_quantity' => $this->validated('min_quantity'),
            'is_active' => true,
        ];
    }

    private function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing->loadMissing('cropType')
            : Listing::with('cropType')->findOrFail($listing);
    }
}
