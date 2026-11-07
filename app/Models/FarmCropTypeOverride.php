<?php

namespace App\Models;

use App\Enums\ListingUnit;
use App\Models\Concerns\LogsCmsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A farm's tighten-only price guard for one crop type.
 *
 * floor_price   raises the farm's floor above the system floor. Never below it.
 * max_discount  lowers the farm's discount ceiling below the system maximum. Never above it.
 *
 * unit          blank for the crop's main unit, or the unit of one of its
 *               extra rows (CropTypeUnitGuard), which this override tightens
 *
 * Null on either price column means the system value applies for that side.
 */
class FarmCropTypeOverride extends Model
{
    use HasFactory, LogsCmsActivity;

    protected $table = 'farm_crop_type_overrides';

    protected $fillable = [
        'farm_id',
        'crop_type_id',
        'unit',
        'floor_price',
        'max_discount',
    ];

    protected function casts(): array
    {
        return [
            'unit' => ListingUnit::class,
            'floor_price' => 'decimal:4',
            'max_discount' => 'decimal:4',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function cropType(): BelongsTo
    {
        return $this->belongsTo(CropType::class);
    }

    public function isEmpty(): bool
    {
        return $this->floor_price === null && $this->max_discount === null;
    }
}
