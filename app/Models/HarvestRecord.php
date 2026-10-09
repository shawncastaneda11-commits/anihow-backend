<?php

namespace App\Models;

use App\Enums\HarvestRecordKind;
use App\Enums\HarvestRejectionReason;
use App\Enums\ListingUnit;
use Database\Factories\HarvestRecordFactory;
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
    'harvested_on',
    'quantity_harvested',
    'quantity_rejected',
    'quantity_good',
    'rejection_reason',
    'rejection_note',
    'price_per_unit',
    'production_cost',
    'cost_breakdown',
    'kind',
    'recorded_by',
])]
class HarvestRecord extends Model
{
    /** @use HasFactory<HarvestRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => ListingUnit::class,
            'is_value_added' => 'boolean',
            'harvested_on' => 'date',
            'quantity_harvested' => 'decimal:2',
            'quantity_rejected' => 'decimal:2',
            'quantity_good' => 'decimal:2',
            'rejection_reason' => HarvestRejectionReason::class,
            'price_per_unit' => 'decimal:4',
            'production_cost' => 'decimal:2',
            'cost_breakdown' => 'array',
            'kind' => HarvestRecordKind::class,
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
