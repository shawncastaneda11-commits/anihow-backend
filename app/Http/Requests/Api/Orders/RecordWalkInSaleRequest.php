<?php

namespace App\Http\Requests\Api\Orders;

use App\Enums\Permission;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordWalkInSaleRequest extends FormRequest
{
    private ?Listing $resolvedListing = null;

    /**
     * Needs the walk-in permission, and the listing must be the seller's own.
     * A missing listing is left to the exists rule, so it answers 422 rather
     * than 403.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can(Permission::RecordWalkInSales->value)) {
            return false;
        }

        $listing = $this->listing();

        return $listing === null || $listing->isOwnedBy($user);
    }

    /**
     * No price field. A walk-in is priced from the listing, the same as an app
     * order, and tawad applies on the same rules. Decision 24.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'listing_id' => [
                'required',
                'integer',
                Rule::exists('listings', 'id')->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            // The cash counted at handover. Recorded, never processed. Allowed
            // to differ from the total, as on an app order. Decision 26.
            'amount_received' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            // For the seller's own reference. Never sent to anyone else.
            'buyer_name' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function listing(): ?Listing
    {
        $id = $this->input('listing_id');

        if (! is_numeric($id)) {
            return null;
        }

        return $this->resolvedListing ??= Listing::query()->find((int) $id);
    }
}
