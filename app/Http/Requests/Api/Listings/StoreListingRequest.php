<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\ListingUnit;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Listing::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')->where('is_active', true)],
            'unit' => ['required', Rule::enum(ListingUnit::class)],
            'price_per_unit' => ['required', 'numeric', 'min:0.01'],
            'quantity_available' => ['required', 'numeric', 'min:0'],
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
