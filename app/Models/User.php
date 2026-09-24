<?php

namespace App\Models;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * One account, one role. There is no dual-account model and no role switching.
 * Assign roles with syncRoles(), never assignRole().
 *
 * shop_name, bio, contact and location are the farmer-seller's storefront.
 */
#[Fillable([
    'name',
    'email',
    'phone',
    'location',
    'farm_id',
    'shop_name',
    'bio',
    'contact',
    'password',
    'status',
    'approved_at',
    'approved_by',
    'suspended_at',
    'suspension_reason',
    'email_verified_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Spatie roles live on the web guard for both Filament (session)
     * and Sanctum (token) users of this model.
     */
    protected string $guard_name = 'web';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Super Admin and Content Editor share one panel, gated per resource by
     * policy. Farmer-Sellers and Buyers use the Android app only.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === UserStatus::Active
            && $this->hasAnyRole(array_column(Role::panelRoles(), 'value'));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin);
    }

    public function isContentEditor(): bool
    {
        return $this->hasRole(Role::ContentEditor);
    }

    public function isFarmerSeller(): bool
    {
        return $this->hasRole(Role::FarmerSeller);
    }

    public function isBuyer(): bool
    {
        return $this->hasRole(Role::Buyer);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isPending(): bool
    {
        return $this->status === UserStatus::Pending;
    }

    public function sendEmailVerificationNotification(): void
    {
        app(SendEmailVerificationCodeAction::class)->handle($this);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'farmer_seller_id');
    }

    public function cropCareArticles(): HasMany
    {
        return $this->hasMany(CropCareArticle::class, 'created_by');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'buyer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function incomingOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'farmer_seller_id');
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

    public function reportsFiled(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function accountDeletionRequests(): HasMany
    {
        return $this->hasMany(AccountDeletionRequest::class);
    }

    /**
     * Placed, Confirmed, or Ready — as buyer or as seller. These block a
     * deletion request because the other party still needs the account.
     */
    public function hasOpenMarketplaceOrders(): bool
    {
        return Order::query()
            ->whereIn('status', [
                OrderStatus::Placed,
                OrderStatus::Confirmed,
                OrderStatus::Ready,
            ])
            ->where(function ($query): void {
                $query->where('buyer_id', $this->id)
                    ->orWhere('farmer_seller_id', $this->id);
            })
            ->exists();
    }

    public function shopContact(): ?string
    {
        return $this->contact ?: $this->phone;
    }

    public function averageRating(): ?string
    {
        $average = array_key_exists('reviews_received_avg_rating', $this->getAttributes())
            ? $this->reviews_received_avg_rating
            : $this->reviewsReceived()->visible()->avg('rating');

        if ($average === null) {
            return null;
        }

        return number_format((float) $average, 1, '.', '');
    }
}
