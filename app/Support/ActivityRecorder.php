<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use App\Models\FaqEntry;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The activity log's single writer.
 *
 * Only changes made by a signed-in administrator or Content Editor are
 * recorded, so a seller editing their own listing in the app is not logged
 * here. Passwords, tokens, and other hidden attributes are never written. A
 * failure to log is reported but never stops the change itself.
 */
final class ActivityRecorder
{
    /** @var list<string> */
    private const NEVER = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'created_at',
        'updated_at',
    ];

    public static function record(string $action, Model $model): void
    {
        if ($model instanceof ActivityLog) {
            return;
        }

        if (self::actor() === null) {
            return;
        }

        try {
            $changes = self::changes($action, $model);

            if ($action === 'updated' && $changes === []) {
                return;
            }

            self::note($action, $model, $changes);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Records an action that is not a plain create, update or delete, such as
     * "signed_out_everywhere" or "role_changed". $subject is a record, or a
     * label for something that is not one (with $area saying who reads it).
     *
     * @param  array<string, mixed>  $changes
     */
    public static function note(string $action, Model|string $subject, array $changes = [], ?string $area = null): void
    {
        $actor = self::actor();

        if ($actor === null) {
            return;
        }

        try {
            $isModel = $subject instanceof Model;

            ActivityLog::query()->create([
                'user_id' => $actor->getKey(),
                'action' => $action,
                'area' => $area ?? ($isModel ? self::area($subject) : ActivityLog::AREA_SYSTEM),
                'subject_type' => $isModel ? $subject->getMorphClass() : $subject,
                'subject_id' => $isModel && is_numeric($subject->getKey()) ? $subject->getKey() : null,
                'subject_label' => $isModel
                    ? (self::label($subject) ?? (is_string($subject->getKey()) ? $subject->getKey() : null))
                    : null,
                'changes' => $changes === [] ? null : $changes,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Records a change of role, which the account's own update does not show
     * because roles are stored apart from the account. A change to or from an
     * administrator role is ICTD's; the account's "created" row is moved with
     * it so ICTD sees the administrator accounts it creates.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     */
    public static function roleChanged(User $user, array $old, array $new): void
    {
        sort($old);
        sort($new);

        if ($old === $new) {
            return;
        }

        $adminRoles = array_column(Role::adminRoles(), 'value');
        $touchesAdmin = array_intersect(array_merge($old, $new), $adminRoles) !== [];
        $area = $touchesAdmin ? ActivityLog::AREA_ADMINS : ActivityLog::AREA_PEOPLE;

        if ($old === [] && $touchesAdmin) {
            ActivityLog::query()
                ->where('subject_type', $user->getMorphClass())
                ->where('subject_id', $user->getKey())
                ->where('action', 'created')
                ->update(['area' => $area]);
        }

        $label = fn (array $names): ?string => $names === []
            ? null
            : implode(', ', array_map(fn (string $name): string => Role::tryFrom($name)?->label() ?? $name, $names));

        self::note('role_changed', $user, ['role' => ['old' => $label($old), 'new' => $label($new)]], $area);
    }

    /**
     * Which office reads a row about this record. An account is an
     * administrator's when it holds, or is losing, an administrator role.
     */
    public static function area(Model $model): string
    {
        if ($model instanceof SystemSetting || $model instanceof CropType || $model instanceof CropTypeUnitGuard) {
            return ActivityLog::AREA_SYSTEM;
        }

        if ($model instanceof FaqEntry) {
            return $model->farm_id === null ? ActivityLog::AREA_SYSTEM : ActivityLog::AREA_PEOPLE;
        }

        if ($model instanceof User) {
            return $model->isAdministrator() ? ActivityLog::AREA_ADMINS : ActivityLog::AREA_PEOPLE;
        }

        return ActivityLog::AREA_PEOPLE;
    }

    /** The signed-in administrator or Content Editor, if any. */
    private static function actor(): ?User
    {
        $actor = auth()->user();

        if (! $actor instanceof User || ! $actor->hasAnyRole(array_column(Role::panelRoles(), 'value'))) {
            return null;
        }

        return $actor;
    }

    /**
     * @return array<string, mixed>
     */
    private static function changes(string $action, Model $model): array
    {
        $skip = array_merge(self::NEVER, $model->getHidden());

        if ($action === 'created') {
            return collect($model->getAttributes())
                ->except($skip)
                ->reject(fn (mixed $value): bool => $value === null)
                ->map(fn (mixed $value): mixed => self::plain($value))
                ->all();
        }

        if ($action !== 'updated') {
            return [];
        }

        $changes = [];

        foreach (array_keys($model->getChanges()) as $field) {
            if (in_array($field, $skip, true)) {
                continue;
            }

            $changes[$field] = [
                'old' => self::plain($model->getRawOriginal($field)),
                'new' => self::plain($model->getAttributes()[$field] ?? null),
            ];
        }

        return $changes;
    }

    private static function plain(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && mb_strlen($value) > 500) {
            return mb_substr($value, 0, 500).'…';
        }

        return $value;
    }

    private static function label(Model $model): ?string
    {
        foreach (['name', 'title', 'email'] as $field) {
            $value = $model->getAttribute($field);

            if (is_string($value) && $value !== '') {
                return mb_strimwidth($value, 0, 120, '…');
            }
        }

        return null;
    }
}
