<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'farmer_seller_id',
    'total',
    'notes',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->farmer_seller_id === $user->id;
    }
}
