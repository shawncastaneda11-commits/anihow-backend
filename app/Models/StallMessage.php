<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stall_conversation_id',
    'user_id',
    'body',
])]
class StallMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $touches = ['conversation'];

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
