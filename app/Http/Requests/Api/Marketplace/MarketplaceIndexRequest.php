<?php

namespace App\Http\Requests\Api\Marketplace;

use App\Enums\GrowingMethod;
use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketplaceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crop_type_id' => ['sometimes', 'integer', 'exists:crop_types,id'],
            'farm_id' => ['sometimes', 'integer', 'exists:farms,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['freshest', 'price_asc', 'price_desc', 'availability'])],
            'category' => ['sometimes', 'nullable', Rule::enum(ProductCategory::class)],
            'growing_method' => ['sometimes', 'nullable', Rule::enum(GrowingMethod::class)],
        ];
    }
}
