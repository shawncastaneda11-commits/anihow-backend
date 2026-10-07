<?php

namespace App\Models;

use Database\Factories\StallConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'buyer_id',
    'farmer_seller_id',
    'buyer_cleared_through_message_id',
    'farmer_seller_cleared_through_message_id',
])]
class StallConversation extends Model
{
    /** @use HasFactory<StallConversationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (StallConversation $conversation): void {
            $conversation->messages()->get()->each(function (StallMessage $message): void {
                $message->delete();
            });
        });
    }

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buyer_cleared_through_message_id' => 'integer',
            'farmer_seller_cleared_through_message_id' => 'integer',
        ];
    }

    /**
     * The caller's own clear point. Null when they have not removed the chat.
     */
    public function clearedThroughFor(User $user): ?int
    {
        $column = $this->clearedColumnFor($user);

        if ($column === null) {
            return null;
        }

        $value = $this->getAttribute($column);

        return $value === null ? null : (int) $value;
    }

    public function clearedColumnFor(User $user): ?string
    {
        if ((int) $this->buyer_id === (int) $user->id) {
            return 'buyer_cleared_through_message_id';
        }

        if ((int) $this->farmer_seller_id === (int) $user->id) {
            return 'farmer_seller_cleared_through_message_id';
        }

        return null;
    }

    /**
     * @param  Builder<StallConversation>  $query
     * @return Builder<StallConversation>
     */
    public function scopeVisibleInInbox(Builder $query, User $user): Builder
    {
        $column = $user->isBuyer()
            ? 'buyer_cleared_through_message_id'
            : 'farmer_seller_cleared_through_message_id';

        return $query->where(function (Builder $query) use ($column): void {
            $query->whereNull($column)
                ->orWhereHas('messages', function (Builder $messages) use ($column): void {
                    $messages->whereColumn('stall_messages.id', '>', 'stall_conversations.'.$column);
                });
        });
    }
}
