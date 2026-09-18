<?php

namespace App\Models;

use App\Enums\ReservationActor;
use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'buyer_id',
    'farmer_seller_id',
    'status',
    'total',
    'notes',
    'cancellation_reason',
    'cancelled_by',
    'ready_at',
    'completed_at',
    'cancelled_at',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'cancelled_by' => ReservationActor::class,
            'total' => 'decimal:2',
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

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function isOwnedByBuyer(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }

    public function isOwnedByFarmer(User $user): bool
    {
        return $this->farmer_seller_id === $user->id;
    }

    public function assertCanTransitionTo(ReservationStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "Cannot change reservation from {$this->status->value} to {$next->value}.",
            ]);
        }
    }
}
