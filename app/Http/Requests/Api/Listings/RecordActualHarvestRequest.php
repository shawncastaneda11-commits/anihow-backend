<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\ProductCategory;
use App\Models\Listing;
use App\Support\HarvestInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RecordActualHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing && ($this->user()?->can('update', $listing) ?? false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return HarvestInput::messages();
    }

    public function rules(): array
    {
        return [
            ...HarvestInput::rules(true),
            'confirm_cancel_reservations' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $listing = $this->route('listing');
                $valueAdded = $listing instanceof Listing
                    && $listing->loadMissing('cropType')->cropType?->category === ProductCategory::ValueAdded;
                HarvestInput::check($validator, $valueAdded);
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
}
