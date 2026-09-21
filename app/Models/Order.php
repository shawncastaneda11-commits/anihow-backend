<?php

namespace App\Models;

use App\Enums\CancellationReason;
use App\Enums\FulfillmentPreference;
use App\Enums\OrderActor;
use App\Enums\OrderSource;
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
 * One order is one farmer-seller and, for an app order, one buyer. A cart
 * holding listings from several sellers is split at checkout into one order
 * for each of them.
 *
 * A walk-in sale has no buyer account. The farmer-seller records it after the
 * handover, so it enters the ledger directly at Completed. No buyer, no
 * review.
 *
 * Cash changes hands in person. This record says that it did. Nothing here
 * processes, holds, or divides money.
 *
 * Status transitions go through App\Services\OrderStateMachine, never through
 * a direct update() on this model. Walk-ins are created by
 * App\Actions\Orders\RecordWalkInSaleAction and never transition afterwards.
 */
#[Fillable([
    'order_number',
    'buyer_id',
    'farmer_seller_id',
    'farm_id',
    'status',
    'source',
    'walk_in_buyer_name',
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

    /**
     * Mirrors the column default, so an order created in memory reads as an
     * app order before it is ever refreshed from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'app',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
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

    public function isWalkIn(): bool
    {
        return $this->source === OrderSource::WalkIn;
    }

    public function isOwnedByBuyer(User $user): bool
    {
        return $this->buyer_id !== null && $this->buyer_id === $user->id;
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
     * A review unlocks only at Completed, one per order. No order, no review,
     * and for a walk-in, no buyer, no review.
     */
    public function canBeReviewed(): bool
    {
        return $this->status === OrderStatus::Completed
            && ! $this->isWalkIn()
            && $this->buyer_id !== null
            && $this->review === null;
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
