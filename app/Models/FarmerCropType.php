<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One crop type on a farmer-seller's own list. The shared taxonomy stays
 * system-wide; this row only chooses which of those crops this farmer lists.
 */
class FarmerCropType extends Model
{
    protected $fillable = [
        'user_id',
        'crop_type_id',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cropType(): BelongsTo
    {
        return $this->belongsTo(CropType::class);
    }
}
