<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A review unlocks at Completed, one per order, tied to that order.
 * No order, no review.
 */
#[Fillable([
    'buyer_id',
    'farmer_seller_id',
    'order_id',
    'rating',
    'comment',
    'is_removed',
    'removed_by',
    'removed_at',
    'removal_reason',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_removed' => 'boolean',
            'removed_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id')->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }

    /**
     * Moderation is a soft removal so the order keeps its one-review link and
     * the buyer cannot post a replacement.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('reviews.is_removed', false);
    }
}
