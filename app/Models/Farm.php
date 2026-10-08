<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\FarmPin;
use App\Support\ImageVariants;
use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Validator;

#[Fillable([
    'name',
    'slug',
    'description',
    'contact_person',
    'contact_number',
    'address',
    'barangay',
    'municipality',
    'pickup_point',
    'cover_photo_path',
    'logo_path',
    'is_active',
    'organic_certifier',
    'organic_certificate_no',
    'organic_certified_until',
    'latitude',
    'longitude',
    'value_added_enabled',
    'reservations_enabled',
    'tawad_enabled',
    'walk_in_enabled',
])]
class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'value_added_enabled' => 'boolean',
            'reservations_enabled' => 'boolean',
            'tawad_enabled' => 'boolean',
            'walk_in_enabled' => 'boolean',
            'organic_certified_until' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public const VALUE_ADDED_OFF_MESSAGE = 'Value-added products are turned off for your farm.';

    public const RESERVATIONS_OFF_MESSAGE = 'This farm is not taking reservations right now.';

    public const TAWAD_OFF_MESSAGE = 'Discounts are turned off for your farm.';

    public const WALK_IN_OFF_MESSAGE = 'Walk-in sales are turned off for your farm.';

    /**
     * CMS labels and the effect shown when a switch is turned off.
     *
     * @return array<string, array{label: string, helper: string}>
     */
    public static function featureSwitches(): array
    {
        return [
            'value_added_enabled' => [
                'label' => 'Value-added products',
                'helper' => 'Sellers can list processed goods (jams, chips, etc.). Off hides them from buyers.',
            ],
            'reservations_enabled' => [
                'label' => 'Reservations',
                'helper' => 'Buyers can reserve upcoming harvests. Off stops new reservations; existing ones finish.',
            ],
            'tawad_enabled' => [
                'label' => 'Tawad (discounts)',
                'helper' => 'Sellers can offer bulk discounts. Off pauses discounts on new orders.',
            ],
            'walk_in_enabled' => [
                'label' => 'Walk-in sales',
                'helper' => 'Sellers can record in-person sales in the app.',
            ],
        ];
    }

    public function allowsValueAdded(): bool
    {
        return $this->value_added_enabled !== false;
    }

    public function allowsReservations(): bool
    {
        return $this->reservations_enabled !== false;
    }

    public function allowsTawad(): bool
    {
        return $this->tawad_enabled !== false;
    }

    public function allowsWalkIn(): bool
    {
        return $this->walk_in_enabled !== false;
    }

    public function hasPin(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function mapsUrl(): ?string
    {
        if (! $this->hasPin()) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='
            .rawurlencode((string) $this->latitude).','
            .rawurlencode((string) $this->longitude);
    }

    /**
     * A certificate counts only while the certifier, the number, and an
     * unexpired date are all on file. Any gap means the farm is not certified.
     */
    public function isOrganicCertified(): bool
    {
        return filled($this->organic_certifier)
            && filled($this->organic_certificate_no)
            && $this->organic_certified_until !== null
            && $this->organic_certified_until->greaterThanOrEqualTo(today());
    }

    /**
     * @param  Builder<Farm>  $query
     * @return Builder<Farm>
     */
    public function scopeOrganicCertified(Builder $query): Builder
    {
        return $query
            ->whereNotNull('organic_certifier')
            ->where('organic_certifier', '!=', '')
            ->whereNotNull('organic_certificate_no')
            ->where('organic_certificate_no', '!=', '')
            ->whereNotNull('organic_certified_until')
            ->whereDate('organic_certified_until', '>=', today());
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function farmerSellers(): HasMany
    {
        return $this->hasMany(User::class)->role(Role::FarmerSeller->value);
    }

    /**
     * Crop types assigned to this farm's sellers. An empty list for a seller
     * means that seller may use every crop type.
     */
    public function sellerCropTypes(): HasManyThrough
    {
        return $this->hasManyThrough(
            FarmerCropType::class,
            User::class,
            'farm_id',
            'user_id',
            'id',
            'id',
        );
    }

    /**
     * One Content Editor per farm. The schema cannot enforce this because the
     * role lives in model_has_roles, so validation and policy do it instead.
     */
    public function contentEditor(): HasOne
    {
        return $this->hasOne(User::class)->role(Role::ContentEditor->value);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(CropCareArticle::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(FarmPhoto::class)->orderBy('sort_order');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(FarmAnnouncement::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(FarmFavorite::class);
    }

    public function faqEntries(): HasMany
    {
        return $this->hasMany(FaqEntry::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Farm $farm): void {
            foreach (['value_added_enabled', 'reservations_enabled', 'tawad_enabled', 'walk_in_enabled'] as $column) {
                if ($farm->{$column} === null) {
                    $farm->{$column} = true;
                }
            }
        });

        static::saving(function (Farm $farm): void {
            foreach (['latitude', 'longitude'] as $column) {
                if ($farm->{$column} === '') {
                    $farm->{$column} = null;
                }
            }

            Validator::make(
                [
                    'latitude' => $farm->latitude,
                    'longitude' => $farm->longitude,
                ],
                FarmPin::rules(),
            )->validate();
        });

        static::updating(function (Farm $farm): void {
            $images = app(ImageVariants::class);

            foreach (['cover_photo_path', 'logo_path'] as $column) {
                if (! $farm->isDirty($column)) {
                    continue;
                }

                $previous = $farm->getOriginal($column);
                $images->delete(is_string($previous) ? $previous : null);
            }
        });

        static::deleting(function (Farm $farm): void {
            $images = app(ImageVariants::class);
            $images->delete($farm->cover_photo_path);
            $images->delete($farm->logo_path);
        });
    }

    public function coverPhotoUrl(): ?string
    {
        return app(ImageVariants::class)->url($this->cover_photo_path);
    }

    public function coverThumbnailUrl(): ?string
    {
        return app(ImageVariants::class)->thumbnailUrl($this->cover_photo_path);
    }

    public function logoUrl(): ?string
    {
        return app(ImageVariants::class)->url($this->logo_path);
    }

    public function logoThumbnailUrl(): ?string
    {
        return app(ImageVariants::class)->thumbnailUrl($this->logo_path);
    }

    /**
     * Tighten-only price guards, one row per crop type this farm has moved off
     * the system value. A missing row, or a null column on a present row, means
     * the system value applies.
     */
    public function cropTypeOverrides(): HasMany
    {
        return $this->hasMany(FarmCropTypeOverride::class);
    }

    /**
     * Reads from the loaded relation when it is present, so the resolver issues
     * no query inside a checkout loop or a Filament table row. Eager load the
     * whole relation, not one constrained row: a constrained eager load
     * resolves a single crop type per farm and misses on the next cart line.
     */
    public function overrideFor(int $cropTypeId): ?FarmCropTypeOverride
    {
        if ($this->relationLoaded('cropTypeOverrides')) {
            return $this->getRelation('cropTypeOverrides')->firstWhere('crop_type_id', $cropTypeId);
        }

        return $this->cropTypeOverrides()
            ->where('crop_type_id', $cropTypeId)
            ->first();
    }

    /**
     * @param  Builder<Farm>  $query
     * @return Builder<Farm>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('farms.is_active', true);
    }
}
