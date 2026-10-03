<?php

namespace App\Models;

use Database\Factories\StallConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'buyer_id',
    'farmer_seller_id',
])]
class StallConversation extends Model
{
    /** @use HasFactory<StallConversationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id')->withTrashed();
    }

    /**
     * @return HasMany<StallMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(StallMessage::class);
    }

    /**
     * @return HasOne<StallMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(StallMessage::class)->latestOfMany();
    }
}
