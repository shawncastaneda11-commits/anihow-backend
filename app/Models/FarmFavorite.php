<?php

namespace App\Models;

use Database\Factories\FarmFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['buyer_id', 'farm_id'])]
class FarmFavorite extends Model
{
    /** @use HasFactory<FarmFavoriteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }
}
