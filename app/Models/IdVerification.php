<?php

namespace App\Models;

use App\Enums\IdDocumentType;
use App\Enums\IdVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's ID check. The photos live on the private disk only until an
 * admin reviews them; after that only the outcome is kept.
 */
#[Fillable([
    'user_id',
    'id_type',
    'front_path',
    'selfie_path',
    'status',
    'rejection_reason',
    'reviewed_by',
    'reviewed_at',
    'consented_at',
])]
class IdVerification extends Model
{
    protected function casts(): array
    {
        return [
            'id_type' => IdDocumentType::class,
            'status' => IdVerificationStatus::class,
            'reviewed_at' => 'datetime',
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === IdVerificationStatus::Pending;
    }

    /**
     * @param  Builder<IdVerification>  $query
     * @return Builder<IdVerification>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', IdVerificationStatus::Pending);
    }
}
