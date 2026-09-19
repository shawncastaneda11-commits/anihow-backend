<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\TawadType;
use App\Models\Listing;
use App\Models\TawadRule;
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
            'discount_amount' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            'min_quantity' => [
                'nullable',
                'required_if:type,'.TawadType::MinimumQuantity->value,
                'numeric',
                'gt:0',
            ],
        ];
    }

    /**
     * Ceiling and floor are checked here as well as at checkout. This one
     * gives the seller an immediate error; the checkout one is the guarantee.
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
                $amount = (float) $this->validated('discount_amount');

                if (! $cropType->allowsDiscount($amount)) {
                    $max = number_format((float) $cropType->max_discount, 2, '.', '');
                    $validator->errors()->add(
                        'discount_amount',
                        "The maximum tawad for {$cropType->name} is PHP {$max}.",
                    );

                    return;
                }

                $rule = new TawadRule([
                    'type' => TawadType::from($this->validated('type')),
                    'discount_amount' => $amount,
                    'min_quantity' => $this->validated('min_quantity'),
                ]);

                if (! $rule->keepsUnitPriceAboveFloor($listing, $cropType)) {
                    $floor = number_format((float) $cropType->floor_price, 2, '.', '');
                    $validator->errors()->add(
                        'discount_amount',
                        "This tawad would bring the unit price below the floor of PHP {$floor}.",
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
