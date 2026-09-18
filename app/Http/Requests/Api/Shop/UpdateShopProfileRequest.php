<?php

namespace App\Http\Requests\Api\Shop;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFarmerSeller() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
