<?php

namespace App\Http\Requests\Api\Chat;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreStallMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sendMessage', $this->route('stallConversation')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:1000'],
            'order_id' => ['sometimes', 'nullable', 'integer'],
            'listing_id' => ['sometimes', 'nullable', 'integer'],
            'attachment' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $body = $this->input('body');
            $hasBody = is_string($body) && trim($body) !== '';

            if (! $hasBody && ! $this->hasFile('attachment')) {
                $validator->errors()->add('body', 'Write a message or attach a photo or PDF.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && is_string($this->input('body'))) {
            $body = trim($this->input('body'));
            $this->merge(['body' => $body === '' ? null : $body]);
        }
    }
}
