<?php

namespace App\Http\Requests\Api\Cart;

use App\Models\CartItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CartItem::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
        ];
    }
}
