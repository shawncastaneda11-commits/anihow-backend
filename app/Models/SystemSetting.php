<?php

namespace App\Models;

use App\Models\Concerns\LogsCmsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A system-wide setting kept by ICTD. Read through get(), which caches for a
 * minute so every API request does not hit the database.
 */
class SystemSetting extends Model
{
    use LogsCmsActivity;

    public const MIN_APP_VERSION = 'min_app_version';

    public const MAINTENANCE_MESSAGE = 'maintenance_message';

    public const REQUIRE_ADMIN_TWO_STEP = 'require_admin_two_step';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::remember('system_settings', 60, fn (): array => self::query()->pluck('value', 'key')->all());

        $value = $all[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value === '' ? null : $value]);
        Cache::forget('system_settings');
    }

    public static function maintenanceMessage(): ?string
    {
        return self::get(self::MAINTENANCE_MESSAGE);
    }

    public static function minimumAppVersion(): ?string
    {
        return self::get(self::MIN_APP_VERSION);
    }

    public static function requiresAdminTwoStep(): bool
    {
        return self::get(self::REQUIRE_ADMIN_TWO_STEP) === '1';
    }
}
