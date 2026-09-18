<?php

namespace App\Models;

use App\Enums\ListingUnit;
use Database\Factories\ReservationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reservation_id',
    'listing_id',
    'listing_name',
    'unit',
    'quantity',
    'unit_price',
    'line_subtotal',
])]
class ReservationItem extends Model
{
    /** @use HasFactory<ReservationItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit' => ListingUnit::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
