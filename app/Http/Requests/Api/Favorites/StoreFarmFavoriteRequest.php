<?php

namespace App\Http\Requests\Api\Favorites;

use App\Models\Farm;
use App\Models\FarmFavorite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FarmFavorite::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'farm_id' => [
                'required',
                'integer',
                'exists:farms,id',
                Rule::unique('farm_favorites', 'farm_id')->where(
                    fn ($query) => $query->where('buyer_id', $this->user()?->id),
                ),
            ],
        ];
    }

    public function farm(): Farm
    {
        return Farm::query()->findOrFail($this->integer('farm_id'));
    }
}
