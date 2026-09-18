<?php

namespace App\Http\Requests\Api\CropCare;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CropCareIndexRequest extends FormRequest
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
            'category_id' => ['sometimes', 'integer', Rule::exists(Category::class, 'id')],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
