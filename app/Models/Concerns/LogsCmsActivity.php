<?php

namespace App\Models\Concerns;

use App\Support\ActivityRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an activity log row when an administrator or a Content Editor
 * creates, changes, or deletes this record. See ActivityRecorder.
 */
trait LogsCmsActivity
{
    public static function bootLogsCmsActivity(): void
    {
        static::created(fn (Model $model) => ActivityRecorder::record('created', $model));
        static::updated(fn (Model $model) => ActivityRecorder::record('updated', $model));
        static::deleted(fn (Model $model) => ActivityRecorder::record('deleted', $model));
    }
}
