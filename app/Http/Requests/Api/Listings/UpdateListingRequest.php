<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\ListingUnit;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            && ($this->user()?->can('update', $listing) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists(Category::class, 'id')->where('is_active', true)],
            'unit' => ['sometimes', 'required', Rule::enum(ListingUnit::class)],
            'price_per_unit' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'quantity_available' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listingAttributes(): array
    {
        return $this->safe()->except('image');
    }
}
