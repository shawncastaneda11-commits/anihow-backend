<?php

namespace App\Http\Requests\Api\Listings;

use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;

class ToggleListingActiveRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
