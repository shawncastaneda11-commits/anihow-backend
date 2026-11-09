<?php

namespace App\Models;

use App\Enums\SellerRequestStatus;
use App\Models\Concerns\LogsCmsActivity;
use Database\Factories\SellerRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Content Editor's request for COSEL to add a farmer-seller to their farm.
 * COSEL creates the account (user_id) or says why not (decision_reason).
 */
#[Fillable([
    'farm_id',
    'requested_by',
    'name',
    'email',
    'phone',
    'note',
    'status',
    'decision_reason',
    'decided_by',
    'decided_at',
    'user_id',
])]
class SellerRequest extends Model
{
    /** @use HasFactory<SellerRequestFactory> */
    use HasFactory;

    use LogsCmsActivity;

    protected function casts(): array
    {
        return [
            'status' => SellerRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isWaiting(): bool
    {
        return $this->status === SellerRequestStatus::Waiting;
    }
}
