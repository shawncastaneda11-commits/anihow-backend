<?php

namespace App\Models;

use App\Enums\ListingUnit;
use App\Enums\TawadType;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every display value is snapshotted at checkout. An order confirmed at a
 * price keeps that price even if the listing or the tawad rule changes later.
 *
 * unit_price is the listed price and is never overwritten by a tawad. The
 * buyer sees three lines: listed price, tawad, final total.
 */
#[Fillable([
    'order_id',
    'listing_id',
    'crop_type_id',
    'listing_name',
    'unit',
    'quantity',
    'unit_price',
    'line_subtotal',
    'tawad_rule_id',
    'tawad_type',
    'tawad_amount',
    'line_total',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit' => ListingUnit::class,
            'tawad_type' => TawadType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'tawad_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function cropType(): BelongsTo
    {
        return $this->belongsTo(CropType::class);
    }

    public function tawadRule(): BelongsTo
    {
        return $this->belongsTo(TawadRule::class);
    }

    public function hasTawad(): bool
    {
        return (float) $this->tawad_amount > 0;
    }
}
