<?php

namespace App\Http\Requests\Api\CropCare;

use App\Models\Category;
use App\Models\CropCareArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCropCareArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('cropCareArticle');

        return $article instanceof CropCareArticle
            && ($this->user()?->can('update', $article) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:10000'],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists(Category::class, 'id')->where('is_active', true)],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * @return array{title?: string, body?: string, category_id?: int}
     */
    public function articleAttributes(): array
    {
        return $this->safe()->only(['title', 'body', 'category_id']);
    }
}
