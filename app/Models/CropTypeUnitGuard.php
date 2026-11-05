<?php

namespace App\Models;

use App\Enums\ListingUnit;
use App\Observers\CropTypeUnitGuardObserver;
use Database\Factories\CropTypeUnitGuardFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price limits for one more selling unit of a crop.
 *
 * The crop's own floor_price and max_discount cover its main unit and every
 * unit that converts to it. A row here covers another unit family: a bundle
 * row lets a kg-guarded crop be sold per bundle. Units in the row's family
 * convert to it (a piece row also covers dozen); package units match only
 * themselves.
 *
 * floor_price   the floor per unit of this row
 * max_discount  the most a tawad may take off each unit, or null for no
 *               maximum (the floor alone limits a tawad); 0 turns tawad off
 */
#[ObservedBy([CropTypeUnitGuardObserver::class])]
class CropTypeUnitGuard extends Model
{
    /** @use HasFactory<CropTypeUnitGuardFactory> */
    use HasFactory;

    protected $fillable = [
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

    public function cropType(): BelongsTo
    {
        return $this->belongsTo(CropType::class);
    }
}
