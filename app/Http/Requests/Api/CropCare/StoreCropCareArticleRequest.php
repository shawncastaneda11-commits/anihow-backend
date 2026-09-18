<?php

namespace App\Http\Requests\Api\CropCare;

use App\Models\Category;
use App\Models\CropCareArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCropCareArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CropCareArticle::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')->where('is_active', true)],
        ];
    }
}
