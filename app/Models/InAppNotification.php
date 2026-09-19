<?php

namespace App\Models;

use App\Enums\NotificationType;
use Database\Factories\InAppNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'type',
    'title',
    'body',
    'related_id',
    'related_type',
    'read_at',
])]
class InAppNotification extends Model
{
    /** @use HasFactory<InAppNotificationFactory> */
    use HasFactory;

    /**
     * Renamed off `notifications`, which is the table Laravel's own database
     * notification channel claims. The old name collided.
     */
    protected $table = 'in_app_notifications';

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function markRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->update(['read_at' => now()]);
    }
}
