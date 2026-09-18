<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'phone',
    'location',
    'shop_name',
    'bio',
    'contact',
    'password',
    'is_active',
    'email_verified_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Spatie roles live on the web guard for both Filament (session)
     * and Sanctum (token) users of this model.
     */
    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasRole(Role::SuperAdmin);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin);
    }

    public function isFarmerSeller(): bool
    {
        return $this->hasRole(Role::FarmerSeller);
    }

    public function isBuyer(): bool
    {
        return $this->hasRole(Role::Buyer);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'farmer_seller_id');
    }

    public function buyerReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'buyer_id');
    }

    public function incomingReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'farmer_seller_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'farmer_seller_id');
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    public function reviewsWritten(): HasMany
    {
        return $this->hasMany(Review::class, 'buyer_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'farmer_seller_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'buyer_id');
    }

    public function shopContact(): ?string
    {
        return $this->contact ?: $this->phone;
    }

    public function averageRating(): ?string
    {
        $average = $this->reviewsReceived()->avg('rating');

        if ($average === null) {
            return null;
        }

        return number_format((float) $average, 2, '.', '');
    }
}
