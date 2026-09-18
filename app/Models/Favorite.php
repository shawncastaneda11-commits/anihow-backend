<?php

namespace App\Models;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['buyer_id', 'listing_id'])]
class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory;

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
