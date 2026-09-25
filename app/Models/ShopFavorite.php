<?php

namespace App\Models;

use Database\Factories\ShopFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['buyer_id', 'farmer_seller_id'])]
class ShopFavorite extends Model
{
    /** @use HasFactory<ShopFavoriteFactory> */
    use HasFactory;

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id')->withTrashed();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }
}
