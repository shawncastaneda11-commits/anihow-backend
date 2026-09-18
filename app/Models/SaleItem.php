<?php

namespace App\Models;

use App\Enums\ListingUnit;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_id',
    'listing_id',
    'listing_name',
    'unit',
    'quantity',
    'unit_price',
    'line_subtotal',
])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
