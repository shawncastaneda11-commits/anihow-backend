<?php

namespace App\Http\Requests\Api\Faq;

use Illuminate\Foundation\Http\FormRequest;

class AskFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('question') && is_string($this->input('question'))) {
            $this->merge(['question' => trim($this->input('question'))]);
        }
    }
}
