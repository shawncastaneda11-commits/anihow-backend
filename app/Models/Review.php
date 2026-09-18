<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'buyer_id',
    'farmer_seller_id',
    'reservation_id',
    'rating',
    'comment',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
