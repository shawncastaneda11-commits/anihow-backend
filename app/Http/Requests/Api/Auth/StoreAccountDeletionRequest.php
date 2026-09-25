<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::RequestAccountDeletion->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
