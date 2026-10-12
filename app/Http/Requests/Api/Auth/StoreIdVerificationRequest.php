<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\IdDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isBuyer();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $image = ['file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'];

        return [
            'id_type' => ['required', Rule::enum(IdDocumentType::class)],
            'front' => ['required', ...$image],
            'selfie' => ['nullable', ...$image],
            'consent' => ['accepted'],
        ];
    }
}
