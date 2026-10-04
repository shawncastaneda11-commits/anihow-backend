<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stall_conversation_id',
    'user_id',
    'body',
    'order_id',
    'listing_id',
    'listing_title',
    'listing_price_per_unit',
    'listing_unit',
    'listing_thumbnail_path',
    'source_order_message_id',
])]
class StallMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $touches = ['conversation'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'listing_price_per_unit' => 'decimal:4',
        ];
    }

    /**
     * History copy must keep the original clock. A normal save would mark the
     * stall thread as updated just now.
     */
    public function saveWithoutTouching(): bool
    {
        $touches = $this->touches;
        $this->touches = [];

        try {
            return $this->save();
        } finally {
            $this->touches = $touches;
        }
    }

    /**
     * @return BelongsTo<StallConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(StallConversation::class, 'stall_conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
