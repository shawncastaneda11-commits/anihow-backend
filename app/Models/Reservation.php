<?php

namespace App\Models;

use App\Enums\FulfillmentPreference;
use App\Enums\ListingUnit;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Enums\TawadType;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A hold on an upcoming harvest. It does not reserve stock and it does not
 * start the 12-hour or 48-hour order timers. Those begin when the row becomes
 * an app order on harvest day.
 */
#[Fillable([
    'buyer_id',
    'listing_id',
    'farmer_seller_id',
    'farm_id',
    'quantity',
    'unit',
    'unit_price',
    'line_subtotal',
    'tawad_amount',
    'line_total',
    'crop_type_id',
    'listing_name',
    'tawad_rule_id',
    'tawad_type',
    'fulfillment_preference',
    'fulfillment_note',
    'status',
    'cancellation_reason',
    'cancellation_note',
    'order_id',
    'converted_at',
    'cancelled_at',
    'active_slot',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit' => ListingUnit::class,
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'tawad_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'tawad_type' => TawadType::class,
            'fulfillment_preference' => FulfillmentPreference::class,
            'status' => ReservationStatus::class,
            'cancellation_reason' => ReservationCancellationReason::class,
            'converted_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === ReservationStatus::Active;
    }

    /**
     * The order-item row captured when the buyer reserved. Conversion copies
     * this array and does not price the line again.
     *
     * @return array<string, mixed>
     */
    public function storedLine(): array
    {
        return [
            'listing_id' => $this->listing_id,
            'crop_type_id' => $this->crop_type_id,
            'listing_name' => $this->listing_name,
            'unit' => $this->unit instanceof ListingUnit ? $this->unit->value : $this->unit,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_subtotal' => $this->line_subtotal,
            'tawad_rule_id' => $this->tawad_rule_id,
            'tawad_type' => $this->tawad_type instanceof TawadType ? $this->tawad_type->value : $this->tawad_type,
            'tawad_amount' => $this->tawad_amount,
            'line_total' => $this->line_total,
        ];
    }
}
