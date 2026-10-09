<?php

namespace App\Models;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use App\Support\ImageVariants;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\HasApiTokens;
use RuntimeException;
use SensitiveParameter;
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
    'accepts_online_payment',
    'payment_time_limit_hours',
    'push_preferences',
    'avatar_path',
    'cover_photo_path',
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
    public const EXPIRED_TEMPORARY_PASSWORD_MESSAGE = 'Your temporary password has expired. Ask the AniHow administrator to reset it.';

    private const TEMPORARY_PASSWORD_ALPHABET = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Spatie roles live on the web guard for both Filament (session)
     * and Sanctum (token) users of this model.
     */
    protected string $guard_name = 'web';

    /**
     * True only while issueTemporaryPassword() is saving, so the password
     * hook leaves the temporary-password flag in place.
     */
    public bool $issuingTemporaryPassword = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'accepts_online_payment' => 'boolean',
            'payment_time_limit_hours' => 'integer',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'must_change_password' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->issuingTemporaryPassword || ! $user->isDirty('password')) {
                return;
            }

            $user->must_change_password = false;
            $user->temporary_password_expires_at = null;
        });

        static::updating(function (User $user): void {
            $images = app(ImageVariants::class);

            foreach (['avatar_path', 'cover_photo_path'] as $column) {
                if ($user->isDirty($column)) {
                    $previous = $user->getOriginal($column);
                    $images->delete(is_string($previous) ? $previous : null);
                }
            }
        });

        static::deleting(function (User $user): void {
            $images = app(ImageVariants::class);
            $images->delete($user->avatar_path);
            $images->delete($user->cover_photo_path);
        });
    }

    /**
     * Super Admin and Content Editor share one panel, gated per resource by
     * policy. Farmer-Sellers and Buyers use the Android app only.
     */
    /**
     * Replace the password with a one-time temporary password, force a change
     * at the next sign-in, and sign the account out of the app.
     */
    public function issueTemporaryPassword(): string
    {
        $plain = $this->generateTemporaryPassword();
        $days = (int) config('anihow.auth.temporary_password_days', 7);

        $this->issuingTemporaryPassword = true;

        try {
            $this->forceFill([
                'password' => $plain,
                'must_change_password' => true,
                'temporary_password_expires_at' => now()->addDays($days),
            ])->save();
        } finally {
            $this->issuingTemporaryPassword = false;
        }

        $this->tokens()->delete();

        return $plain;
    }

    public function hasExpiredTemporaryPassword(): bool
    {
        return $this->must_change_password
            && $this->temporary_password_expires_at !== null
            && $this->temporary_password_expires_at->isPast();
    }

    public static function temporaryPasswordGuidance(): string
    {
        $days = (int) config('anihow.auth.temporary_password_days', 7);

        return "Give this to the user in person. It expires in {$days} days and must be changed at first sign-in.";
    }

    public function temporaryPasswordNotice(#[SensitiveParameter] string $plainPassword): string
    {
        return $plainPassword."\n\n".$this->email."\n\n".self::temporaryPasswordGuidance();
    }

    private function generateTemporaryPassword(): string
    {
        $alphabet = self::TEMPORARY_PASSWORD_ALPHABET;
        $last = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $chars = '';

            for ($index = 0; $index < 12; $index++) {
                $chars .= $alphabet[random_int(0, $last)];
            }

            if (! preg_match('/[a-z]/', $chars) || ! preg_match('/[A-Z]/', $chars) || ! preg_match('/[2-9]/', $chars)) {
                continue;
            }

            $plain = substr($chars, 0, 4).'-'.substr($chars, 4, 4).'-'.substr($chars, 8, 4);

            $validator = Validator::make(
                ['password' => $plain],
                ['password' => ['required', Password::defaults()]],
            );

            if (! $validator->fails()) {
                return $plain;
            }
        }

        throw new RuntimeException('Could not generate a temporary password.');
    }

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

    /**
     * The seller has turned online payment on. A QR is still required before
     * checkout will accept it. Null follows the column default, which is off.
     */
    public function acceptsOnlinePayment(): bool
    {
        return $this->accepts_online_payment === true;
    }

    public function paymentQrs(): HasMany
    {
        return $this->hasMany(SellerPaymentQr::class, 'farmer_seller_id');
    }

    public function isPending(): bool
    {
        return $this->status === UserStatus::Pending;
    }

    public function sendEmailVerificationNotification(): void
    {
        app(SendEmailVerificationCodeAction::class)->handle($this);
    }

    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
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

    /**
     * Crop types this farmer-seller may list. An empty list means every crop
     * type. The first row turns it into their own list.
     */
    public function farmerCropTypes(): HasMany
    {
        return $this->hasMany(FarmerCropType::class);
    }

    public function mayUseCropType(int|string $cropTypeId): bool
    {
        if ($this->isFarmerSeller() && ! CropType::query()->forFarm($this->farm_id)->whereKey($cropTypeId)->exists()) {
            return false;
        }

        if (! $this->isFarmerSeller() || ! $this->farmerCropTypes()->exists()) {
            return true;
        }

        return $this->farmerCropTypes()->where('crop_type_id', $cropTypeId)->exists();
    }

    public function cropCareArticles(): HasMany
    {
        return $this->hasMany(CropCareArticle::class, 'created_by');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'buyer_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'buyer_id');
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

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Missing keys stay on. A null column is the default set.
     *
     * @return Attribute<array{orders: bool, payments: bool, chats: bool, farm_updates: bool}, array<string, bool>>
     */
    protected function pushPreferences(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                $stored = is_string($value) ? json_decode($value, true) : $value;
                $stored = is_array($stored) ? $stored : [];

                return [
                    'orders' => (bool) ($stored['orders'] ?? true),
                    'payments' => (bool) ($stored['payments'] ?? true),
                    'chats' => (bool) ($stored['chats'] ?? true),
                    'farm_updates' => (bool) ($stored['farm_updates'] ?? true),
                ];
            },
            set: function (array $value): string {
                return json_encode([
                    'orders' => (bool) ($value['orders'] ?? true),
                    'payments' => (bool) ($value['payments'] ?? true),
                    'chats' => (bool) ($value['chats'] ?? true),
                    'farm_updates' => (bool) ($value['farm_updates'] ?? true),
                ], JSON_THROW_ON_ERROR);
            },
        );
    }

    public function allowsPushCategory(?string $category): bool
    {
        if ($category === null || $category === 'general') {
            return true;
        }

        return $this->push_preferences[$category] ?? true;
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

    public function shopFavorites(): HasMany
    {
        return $this->hasMany(ShopFavorite::class, 'buyer_id');
    }

    public function farmFavorites(): HasMany
    {
        return $this->hasMany(FarmFavorite::class, 'buyer_id');
    }

    public function shopFans(): HasMany
    {
        return $this->hasMany(ShopFavorite::class, 'farmer_seller_id');
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
     * Placed, Confirmed, or Ready — as buyer or as seller — plus any refund
     * that has not been marked sent. These block a deletion request because
     * the other party still needs the account.
     */
    public function hasOpenMarketplaceOrders(): bool
    {
        return Order::query()
            ->where(function ($query): void {
                $query->where('buyer_id', $this->id)
                    ->orWhere('farmer_seller_id', $this->id);
            })
            ->where(function ($query): void {
                $query->whereIn('status', [
                    OrderStatus::Placed,
                    OrderStatus::Confirmed,
                    OrderStatus::Ready,
                ])->orWhere('payment_status', OrderPaymentStatus::RefundDue);
            })
            ->exists();
    }

    public function hasRefundDue(): bool
    {
        return Order::query()
            ->where('payment_status', OrderPaymentStatus::RefundDue)
            ->where(function ($query): void {
                $query->where('buyer_id', $this->id)
                    ->orWhere('farmer_seller_id', $this->id);
            })
            ->exists();
    }

    /**
     * A reservation still waiting on a payment check, still holding a paid
     * quantity, or still owed a refund. These block account deletion.
     */
    public function hasBlockingReservationPayments(): bool
    {
        return Reservation::query()
            ->where(function ($query): void {
                $query->where('buyer_id', $this->id)
                    ->orWhere('farmer_seller_id', $this->id);
            })
            ->where(function ($query): void {
                $query->where('payment_status', OrderPaymentStatus::PaymentSent)
                    ->orWhere(function ($paid): void {
                        $paid->where('payment_status', OrderPaymentStatus::Paid)
                            ->where('status', ReservationStatus::Active);
                    })
                    ->orWhere('payment_status', OrderPaymentStatus::RefundDue);
            })
            ->exists();
    }

    /**
     * Seller orders still waiting on a payment or a refund. Used only to warn
     * a Super Admin before they suspend the account. Nothing is changed.
     */
    public function unsettledPaymentOrderCount(): int
    {
        return Order::query()
            ->where('farmer_seller_id', $this->id)
            ->where(function ($query): void {
                $query->whereIn('payment_status', [
                    OrderPaymentStatus::AwaitingPayment,
                    OrderPaymentStatus::PaymentSent,
                    OrderPaymentStatus::RefundDue,
                ])->orWhere(function ($paid): void {
                    $paid->where('payment_status', OrderPaymentStatus::Paid)
                        ->where('status', '!=', OrderStatus::Completed);
                });
            })
            ->count();
    }

    public function shopContact(): ?string
    {
        return $this->contact ?: $this->phone;
    }

    public function avatarUrl(): ?string
    {
        return app(ImageVariants::class)->url($this->avatar_path);
    }

    public function coverUrl(): ?string
    {
        return app(ImageVariants::class)->url($this->cover_photo_path);
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
