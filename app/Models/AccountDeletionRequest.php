<?php

namespace App\Models;

use App\Enums\AccountDeletionStatus;
use Database\Factories\AccountDeletionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'reason',
    'status',
    'processed_by',
    'processed_at',
    'rejection_note',
])]
class AccountDeletionRequest extends Model
{
    /** @use HasFactory<AccountDeletionRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AccountDeletionStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === AccountDeletionStatus::Pending;
    }

    /**
     * @param  Builder<AccountDeletionRequest>  $query
     * @return Builder<AccountDeletionRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AccountDeletionStatus::Pending);
    }
}
