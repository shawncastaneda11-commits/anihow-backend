<?php

namespace App\Enums;

/**
 * Farmer-seller accounts are created by the Super Admin, so there is no
 * pending-approval state: an account is active until it is suspended.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
