<?php

namespace App\Http\Requests\Api\Shop;

use App\Support\FarmPin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyerShopIndexRequest extends FormRequest
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
            'sort' => ['sometimes', 'nullable', Rule::in(['nearest'])],
            ...FarmPin::nearRules(),
        ];
    }
}
