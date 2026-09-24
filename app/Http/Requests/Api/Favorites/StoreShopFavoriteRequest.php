<?php

namespace App\Http\Requests\Api\Favorites;

use App\Models\ShopFavorite;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ShopFavorite::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'farmer_seller_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('shop_favorites', 'farmer_seller_id')->where(
                    fn ($query) => $query->where('buyer_id', $this->user()?->id),
                ),
            ],
        ];
    }

    public function farmerSeller(): User
    {
        return User::query()->findOrFail($this->integer('farmer_seller_id'));
    }
}
