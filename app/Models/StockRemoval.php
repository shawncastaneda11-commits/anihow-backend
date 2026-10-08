<?php

namespace App\Models;

use App\Enums\ListingUnit;
use App\Enums\StockRemovalReason;
use Database\Factories\StockRemovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'listing_id',
    'farmer_seller_id',
    'farm_id',
    'crop_type_id',
    'unit',
    'is_value_added',
    'quantity',
    'reason',
    'note',
    'price_per_unit',
    'recorded_by',
])]
class StockRemoval extends Model
{
    /** @use HasFactory<StockRemovalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => ListingUnit::class,
            'is_value_added' => 'boolean',
            'quantity' => 'decimal:2',
            'reason' => StockRemovalReason::class,
            'price_per_unit' => 'decimal:4',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function cropType(): BelongsTo
    {
        return $this->belongsTo(CropType::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
