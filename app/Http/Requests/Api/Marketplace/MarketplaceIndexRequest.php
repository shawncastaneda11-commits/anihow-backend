<?php

namespace App\Http\Requests\Api\Marketplace;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketplaceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBuyer() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', Rule::exists(Category::class, 'id')],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in(['price_asc', 'price_desc', 'freshest', 'availability'])],
        ];
    }
}
