<?php

namespace App\Http\Requests\Api\Favorites;

use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Favorite::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'listing_id' => [
                'required',
                'integer',
                'exists:listings,id',
                Rule::unique('favorites', 'listing_id')->where(
                    fn ($query) => $query->where('buyer_id', $this->user()?->id),
                ),
            ],
        ];
    }

    public function listing(): Listing
    {
        return Listing::query()->findOrFail($this->integer('listing_id'));
    }
}
