<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * There is no carts table: the buyer is the cart. The cart spans farms and
 * sellers freely, and the split into one order per seller happens at checkout.
 */
#[Fillable(['buyer_id', 'listing_id', 'quantity'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }

    public function lineSubtotal(): float
    {
        return (float) $this->quantity * (float) $this->listing->price_per_unit;
    }
}
