<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change made in the CMS by an administrator or a Content Editor.
 *
 * changes  {"field": {"old": ..., "new": ...}} for updates; the new values for
 *          a created record; empty for a deletion. Passwords and tokens are
 *          never recorded.
 * area     Which office reads the row: AREA_PEOPLE (COSEL), AREA_ADMINS
 *          (ICTD), or AREA_SYSTEM (both).
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** Accounts of farmer-sellers, buyers and Content Editors; farms; listings. */
    public const AREA_PEOPLE = 'people';

    /** Administrator accounts (ICTD and COSEL). */
    public const AREA_ADMINS = 'admins';

    /** Crop types, the system FAQ, system settings and jobs. */
    public const AREA_SYSTEM = 'system';

    /** What each action is called on screen. */
    public const ACTIONS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'role_changed' => 'Role changed',
        'signed_out_everywhere' => 'Signed out everywhere',
        'buying_turned_on' => 'Buying turned on',
        'buying_turned_off' => 'Buying turned off',
        'two_step_turned_off' => 'Two-step sign-in turned off',
        'retried_failed_jobs' => 'Retried failed jobs',
        'announced' => 'Announced',
        'exported' => 'Exported',
    ];

    protected $attributes = [
        'area' => self::AREA_PEOPLE,
    ];

    protected $fillable = [
        'user_id',
        'action',
        'area',
        'subject_type',
        'subject_id',
        'subject_label',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * COSEL reads people and system rows; ICTD reads administrator and system
     * rows, so ICTD never sees the personal details in people's accounts.
     *
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        $areas = [self::AREA_SYSTEM];

        if ($viewer->can(Permission::ManageAccounts->value)) {
            $areas[] = self::AREA_PEOPLE;
        }

        if ($viewer->can(Permission::ManageAdminAccounts->value)) {
            $areas[] = self::AREA_ADMINS;
        }

        return $query->whereIn('area', $areas);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Listing", "Crop type", from the subject's class name. */
    public function subjectName(): string
    {
        $named = [
            'User' => 'Account',
            'IdVerification' => 'ID check',
            'SellerRequest' => 'Seller request',
            'FaqEntry' => 'FAQ answer',
            'TawadRule' => 'Tawad',
            'CropTypeUnitGuard' => 'Price limit for a unit',
            'FarmCropTypeOverride' => 'Farm price guard',
        ][class_basename($this->subject_type)] ?? null;

        if ($named !== null) {
            return $named;
        }

        return ucfirst(strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($this->subject_type)))));
    }

    /** One readable line, such as "price_per_unit: 20 → 25; status: active → suspended". */
    public function changeSummary(): string
    {
        $changes = $this->changes ?? [];

        if ($changes === []) {
            return '';
        }

        return collect($changes)
            ->map(function (mixed $value, string $field): string {
                if (is_array($value) && array_key_exists('new', $value)) {
                    return $field.': '.self::text($value['old'] ?? null).' → '.self::text($value['new']);
                }

                return $field.': '.self::text($value);
            })
            ->implode('; ');
    }

    private static function text(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(blank)';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return mb_strimwidth((string) $value, 0, 80, '…');
    }
}
