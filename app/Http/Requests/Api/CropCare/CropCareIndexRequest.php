<?php

namespace App\Http\Requests\Api\CropCare;

use App\Enums\ArticleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CropCareIndexRequest extends FormRequest
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
            'category' => ['sometimes', Rule::enum(ArticleCategory::class)],
            'search' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
