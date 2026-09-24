<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use Database\Factories\FarmAnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'farm_id',
    'author_id',
    'title',
    'body',
    'audience',
    'starts_at',
    'ends_at',
    'is_pinned',
])]
class FarmAnnouncement extends Model
{
    /** @use HasFactory<FarmAnnouncementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_pinned' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function belongsToFarm(int $farmId): bool
    {
        return (int) $this->farm_id === $farmId;
    }

    /**
     * Active = already started (or no start) and not yet ended (or no end).
     */
    public function isCurrentlyActive(): bool
    {
        $now = now();

        $started = $this->starts_at === null || $this->starts_at->lte($now);
        $notEnded = $this->ends_at === null || $this->ends_at->gt($now);

        return $started && $notEnded;
    }

    /**
     * @param  Builder<FarmAnnouncement>  $query
     * @return Builder<FarmAnnouncement>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function (Builder $inner) use ($now): void {
                $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $inner) use ($now): void {
                $inner->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            });
    }

    /**
     * @param  Builder<FarmAnnouncement>  $query
     * @return Builder<FarmAnnouncement>
     */
    public function scopePublicAudience(Builder $query): Builder
    {
        return $query->where('farm_announcements.audience', AnnouncementAudience::Public);
    }

    /**
     * @param  Builder<FarmAnnouncement>  $query
     * @return Builder<FarmAnnouncement>
     */
    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('farm_announcements.farm_id', $farmId);
    }
}
