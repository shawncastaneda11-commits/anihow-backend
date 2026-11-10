<?php

namespace App\Models;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Actions\Reservations\CancelReservation;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Concerns\LogsCmsActivity;
use App\Notifications\ResetPasswordNotification;
use App\Support\ImageVariants;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
    'terms_accepted_at',
    'terms_version',
    'requires_id_verification',
    'buying_enabled_at',
    'buying_enabled_via',
    'id_verified_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasEmailAuthentication, MustVerifyEmail
{
    use LogsCmsActivity;

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
            'has_email_authentication' => 'boolean',
            'last_app_seen_at' => 'datetime',
            'temporary_password_expires_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'terms_version' => 'integer',
            'requires_id_verification' => 'boolean',
            'buying_enabled_at' => 'datetime',
            'id_verified_at' => 'datetime',
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

        /*
         * However an account is suspended (the Suspend action, the Status
         * field, an anonymised deletion), its app sessions end with it.
         */
        static::updated(function (User $user): void {
            if ($user->wasChanged('status') && ! $user->status->canAuthenticate()) {
                $user->tokens()->delete();
            }
        });

        static::deleting(function (User $user): void {
            $images = app(ImageVariants::class);
            $images->delete($user->avatar_path);
            $images->delete($user->cover_photo_path);
        });

        /*
         * A deleted account keeps its row for the orders and chats it took
         * part in, but gives up its email, so the person can sign up again
         * or be given a new account with it. Its app sessions and
         * reservations end with it.
         */
        static::softDeleted(function (User $user): void {
            if ($user->email !== $user->releasedEmail()) {
                $user->forceFill(['email' => $user->releasedEmail()])->saveQuietly();
            }

            $user->tokens()->delete();
            $user->deviceTokens()->delete();

            $cancelReservation = app(CancelReservation::class);
            $cancelReservation->forBuyer($user, ReservationCancellationReason::AccountClosed);
            $cancelReservation->forSeller($user, ReservationCancellationReason::AccountClosed);
        });
    }

    /**
     * Why the account can't be deleted yet, or null when it can. An order
     * still in progress needs both people until it is completed or cancelled.
     */
    public function deletionBlocker(): ?string
    {
        if (! $this->hasOpenMarketplaceOrders()) {
            return null;
        }

        return 'This account has orders still in progress or a refund due. Complete or cancel them first, or suspend the account instead.';
    }

    /**
     * The placeholder email a deleted account holds, freeing its real one.
     */
    public function releasedEmail(): string
    {
        return "deleted-{$this->getKey()}@anihow.invalid";
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

    /**
     * Ends every session: app tokens, CMS sessions and "remember me". The
     * password is unchanged.
     */
    public function signOutEverywhere(): void
    {
        $this->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $this->getKey())
                ->delete();
        }

        $this->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    /** Two-step sign-in to the CMS with a code sent by email (Filament). */
    public function hasEmailAuthentication(): bool
    {
        return (bool) $this->has_email_authentication;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->forceFill(['has_email_authentication' => $condition])->save();
    }

    /** COSEL's Account Manager. */
    public function isAccountManager(): bool
    {
        return $this->hasRole(Role::AccountManager);
    }

    /** An administrator: the ICTD Super Admin or the COSEL Account Manager. */
    public function isAdministrator(): bool
    {
        return $this->hasAnyRole(array_column(Role::adminRoles(), 'value'));
    }

    /**
     * The office line under the logo in the CMS, such as "COSEL · Account
     * Manager" or "Sanctuario Nature Farm · Content Editor".
     */
    public function officeLabel(): ?string
    {
        return match (true) {
            $this->hasRole(Role::SuperAdmin->value) => 'ICTD · Super Admin',
            $this->hasRole(Role::AccountManager->value) => 'COSEL · Account Manager',
            $this->hasRole(Role::ContentEditor->value) => ($this->farm?->name ?? 'Farm').' · Content Editor',
            default => null,
        };
    }

    public function isContentEditor(): bool
    {
        return $this->hasRole(Role::ContentEditor);
    }

    /**
     * App users must accept the current terms before using the app. CMS-only
     * staff (admins and content editors) are not asked.
     */
    public function needsTermsAcceptance(): bool
    {
        if (! $this->isBuyer() && ! $this->isFarmerSeller()) {
            return false;
        }

        return $this->terms_version === null
            || $this->terms_version < (int) config('anihow.terms_version');
    }

    public function acceptCurrentTerms(): void
    {
        $this->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => (int) config('anihow.terms_version'),
        ])->save();
    }

    /**
     * True when this buyer must pass an ID check before checking out or
     * reserving. Only accounts made through sign-up after the feature
     * launched are required; older accounts are never blocked.
     */
    public function mustVerifyIdToOrder(): bool
    {
        return (bool) config('anihow.require_id_verification')
            && $this->requires_id_verification
            && $this->id_verified_at === null;
    }

    public function idVerifications(): HasMany
    {
        return $this->hasMany(IdVerification::class);
    }

    public function latestIdVerification(): HasOne
    {
        return $this->hasOne(IdVerification::class)->latestOfMany();
    }

    public function isFarmerSeller(): bool
    {
        return $this->hasRole(Role::FarmerSeller);
    }

    public function isBuyer(): bool
    {
        return $this->hasRole(Role::Buyer);
    }

    /**
     * A farmer-seller who has turned buying on: one account, both sides.
     */
    public function alsoBuys(): bool
    {
        return $this->isFarmerSeller() && $this->isBuyer();
    }

    /**
     * The role an account is known by, for an account that holds the buyer
     * role beside another (a farmer-seller who also buys).
     */
    public function primaryRole(): ?Role
    {
        $names = $this->getRoleNames();

        foreach ([Role::SuperAdmin, Role::AccountManager, Role::ContentEditor, Role::FarmerSeller, Role::Buyer] as $role) {
            if ($names->contains($role->value)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Lets a farmer-seller buy with the same account. Like any new buyer,
     * they send an ID before their first order. $via is "app" when the
     * seller turned it on, "cms" when COSEL did.
     */
    public function turnOnBuying(string $via): void
    {
        if (! $this->isFarmerSeller() || $this->isBuyer()) {
            return;
        }

        $this->assignRole(Role::Buyer);
        $this->forceFill([
            'buying_enabled_at' => now(),
            'buying_enabled_via' => $via,
            'requires_id_verification' => $this->id_verified_at === null ? true : $this->requires_id_verification,
        ])->save();
    }

    /**
     * Ends buying only. Orders already placed as a buyer stay as they are,
     * and selling is not affected.
     */
    public function turnOffBuying(): void
    {
        if (! $this->isFarmerSeller()) {
            return;
        }

        $this->removeRole(Role::Buyer);
        $this->forceFill(['buying_enabled_at' => null, 'buying_enabled_via' => null])->save();
    }

    /**
     * After the CMS saves an account's role (which replaces its roles with
     * the one chosen), puts back the buyer role of a farmer-seller who also
     * buys, and forgets buying for an account that is no longer a seller.
     */
    public function keepBuyingInStep(): void
    {
        $this->unsetRelation('roles');

        if ($this->buying_enabled_at === null) {
            return;
        }

        if ($this->isFarmerSeller()) {
            if (! $this->isBuyer()) {
                $this->assignRole(Role::Buyer);
            }

            return;
        }

        $this->forceFill(['buying_enabled_at' => null, 'buying_enabled_via' => null])->save();
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
    /**
     * The seller's online orders not yet completed or cancelled.
     */
    public function ordersInProgressAsSellerCount(): int
    {
        return Order::query()
            ->where('farmer_seller_id', $this->id)
            ->where('source', '!=', OrderSource::WalkIn)
            ->whereIn('status', [OrderStatus::Placed, OrderStatus::Confirmed, OrderStatus::Ready])
            ->count();
    }

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
