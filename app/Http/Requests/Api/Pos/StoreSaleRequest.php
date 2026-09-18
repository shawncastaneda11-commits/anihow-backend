<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Sale::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.listing_id' => ['required', 'integer', 'exists:listings,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return list<array{listing_id: int, quantity: mixed}>
     */
    public function items(): array
    {
        return $this->validated('items');
    }
}
