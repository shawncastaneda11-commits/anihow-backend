<?php

namespace App\Http\Requests\Api\Chat;

use App\Models\StallConversation;
use Illuminate\Foundation\Http\FormRequest;

class StoreStallConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StallConversation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'farmer_seller_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
