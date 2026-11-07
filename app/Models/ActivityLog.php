<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change made in the CMS by an administrator or a Content Editor.
 *
 * changes  {"field": {"old": ..., "new": ...}} for updates; the new values for
 *          a created record; empty for a deletion. Passwords and tokens are
 *          never recorded.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Listing", "Crop type", from the subject's class name. */
    public function subjectName(): string
    {
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
