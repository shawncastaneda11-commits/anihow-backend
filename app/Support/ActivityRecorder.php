<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\ActivityLog;
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

        $actor = auth()->user();

        if (! $actor instanceof User || ! $actor->hasAnyRole(array_column(Role::panelRoles(), 'value'))) {
            return;
        }

        try {
            $changes = self::changes($action, $model);

            if ($action === 'updated' && $changes === []) {
                return;
            }

            ActivityLog::query()->create([
                'user_id' => $actor->getKey(),
                'action' => $action,
                'subject_type' => $model->getMorphClass(),
                'subject_id' => $model->getKey(),
                'subject_label' => self::label($model),
                'changes' => $changes === [] ? null : $changes,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
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
