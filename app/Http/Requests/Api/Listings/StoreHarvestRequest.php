<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\ProductCategory;
use App\Models\Listing;
use App\Support\HarvestInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing && ($this->user()?->can('update', $listing) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return HarvestInput::rules(true);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                HarvestInput::check($validator, $this->valueAdded());
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function harvest(): array
    {
        return HarvestInput::normalize($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(HarvestInput::prepare($this->all()));
    }

    private function valueAdded(): bool
    {
        $listing = $this->route('listing');

        if (! $listing instanceof Listing) {
            return false;
        }

        $listing->loadMissing('cropType');

        return $listing->cropType?->category === ProductCategory::ValueAdded;
    }
}
