<?php

namespace App\Models;

use App\Enums\CancellationReason;
use App\Enums\FulfillmentPreference;
use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

/**
 * One order is one buyer and one farmer-seller. A cart holding listings from
 * several sellers is split at checkout into one order for each of them.
 *
 * Cash changes hands in person. This record says that it did. Nothing here
 * processes, holds, or divides money.
 *
 * Status transitions go through App\Services\OrderStateMachine, never through
 * a direct update() on this model.
 */
#[Fillable([
    'order_number',
    'buyer_id',
    'farmer_seller_id',
    'farm_id',
    'status',
    'fulfillment_preference',
    'fulfillment_note',
    'payment_method',
    'subtotal',
    'tawad_total',
    'total',
    'amount_received',
    'notes',
    'cancellation_reason',
    'cancelled_by',
    'cancellation_note',
    'confirmed_at',
    'ready_at',
    'completed_at',
    'cancelled_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'fulfillment_preference' => FulfillmentPreference::class,
            'cancellation_reason' => CancellationReason::class,
            'cancelled_by' => OrderActor::class,
            'subtotal' => 'decimal:2',
            'tawad_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function isOwnedByBuyer(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }

    public function isOwnedByFarmer(User $user): bool
    {
        return $this->farmer_seller_id === $user->id;
    }

    public function assertCanTransitionTo(OrderStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "Cannot change order from {$this->status->value} to {$next->value}.",
            ]);
        }
    }

    /**
     * A review unlocks only at Completed, one per order. No order, no review.
     */
    public function canBeReviewed(): bool
    {
        return $this->status === OrderStatus::Completed && $this->review === null;
    }

    /**
     * A buyer may cancel only before the seller confirms.
     */
    public function canBeCancelledByBuyer(): bool
    {
        return $this->status === OrderStatus::Placed;
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('orders.farm_id', $farmId);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('orders.status', OrderStatus::Completed);
    }
}
