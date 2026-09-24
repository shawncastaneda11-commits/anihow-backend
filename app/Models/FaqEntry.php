<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\FaqEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'farm_id',
    'intent_key',
    'roles',
    'label',
    'label_fil',
    'keywords',
    'answer',
    'answer_fil',
    'sort_order',
    'is_active',
])]
class FaqEntry extends Model
{
    /** @use HasFactory<FaqEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'keywords' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function isSystemWide(): bool
    {
        return $this->farm_id === null;
    }

    public function belongsToFarm(int $farmId): bool
    {
        return $this->farm_id !== null && (int) $this->farm_id === $farmId;
    }

    public function isFarmerSellerFacingOnly(): bool
    {
        $roles = array_values(array_unique($this->roles ?? []));

        return $roles === [Role::FarmerSeller->value];
    }

    public function localized(string $locale, string $key): string
    {
        if ($locale === 'fil' && filled($this->{$key.'_fil'})) {
            return (string) $this->{$key.'_fil'};
        }

        return (string) $this->{$key};
    }

    /**
     * @param  Builder<FaqEntry>  $query
     * @return Builder<FaqEntry>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('faq_entries.is_active', true);
    }

    /**
     * @param  Builder<FaqEntry>  $query
     * @return Builder<FaqEntry>
     */
    public function scopeSystemWide(Builder $query): Builder
    {
        return $query->whereNull('faq_entries.farm_id');
    }

    /**
     * @param  Builder<FaqEntry>  $query
     * @return Builder<FaqEntry>
     */
    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('faq_entries.farm_id', $farmId);
    }

    /**
     * @param  Builder<FaqEntry>  $query
     * @return Builder<FaqEntry>
     */
    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereJsonContains('roles', $role);
    }
}
