<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ExportOwnData->value) ?? false;
    }

    /**
     * Email is the OTP-verified login and is never accepted here, even if sent.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
