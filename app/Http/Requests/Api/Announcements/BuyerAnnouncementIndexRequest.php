<?php

namespace App\Http\Requests\Api\Announcements;

use Illuminate\Foundation\Http\FormRequest;

class BuyerAnnouncementIndexRequest extends FormRequest
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
            'farm_id' => ['sometimes', 'integer', 'exists:farms,id'],
            'following' => ['sometimes', 'boolean'],
        ];
    }
}
