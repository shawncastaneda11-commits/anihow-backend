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
     * Shared with reservation quantity so a reserve and a cart line accept
     * the same numbers, including a decimal quantity for the unit.
     *
     * @return array<int, string>
     */
    public static function quantityRules(): array
    {
        return ['required', 'numeric', 'gt:0', 'max:99999.99'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'quantity' => self::quantityRules(),
        ];
    }
}
