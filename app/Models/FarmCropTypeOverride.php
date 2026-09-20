<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A farm's tighten-only price guard for one crop type.
 *
 * floor_price   raises the farm's floor above the system floor. Never below it.
 * max_discount  lowers the farm's discount ceiling below the system maximum. Never above it.
 *
 * Null on either column means the system value applies for that side.
 */
class FarmCropTypeOverride extends Model
{
    use HasFactory;

    protected $table = 'farm_crop_type_overrides';

    protected $fillable = [
        'farm_id',
        'crop_type_id',
        'floor_price',
        'max_discount',
    ];

    protected function casts(): array
    {
        return [
            'floor_price' => 'decimal:2',
            'max_discount' => 'decimal:2',
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
