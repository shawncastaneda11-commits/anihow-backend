<?php

namespace App\Http\Requests\Api\Listings;

use App\Enums\StockRemovalReason;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockRemovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing && ($this->user()?->can('update', $listing) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999.99'],
            'reason' => ['required', Rule::enum(StockRemovalReason::class)],
            'note' => ['nullable', 'string', 'max:255', 'required_if:reason,'.StockRemovalReason::Correction->value],
        ];
    }
}
