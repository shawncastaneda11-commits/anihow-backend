<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    'is_active',
])]
class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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

    /**
     * @param  Builder<Farm>  $query
     * @return Builder<Farm>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('farms.is_active', true);
    }
}
