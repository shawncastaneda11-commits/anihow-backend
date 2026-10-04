<?php

namespace App\Models;

use App\Support\ImageVariants;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
    'attachment_path',
    'attachment_mime',
    'attachment_size',
    'attachment_name',
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
            'attachment_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (StallMessage $message): void {
            $message->deleteStoredAttachment();
        });
    }

    public function deleteStoredAttachment(): void
    {
        if (! filled($this->attachment_path)) {
            return;
        }

        $disk = Storage::disk('local');

        if (str_starts_with((string) $this->attachment_mime, 'image/')) {
            app(ImageVariants::class)->delete($this->attachment_path, $disk);

            return;
        }

        $disk->delete($this->attachment_path);
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

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
