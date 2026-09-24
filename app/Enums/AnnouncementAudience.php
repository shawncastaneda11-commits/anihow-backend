<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case Members = 'members';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Members => 'Members',
            self::Public => 'Public',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $audience): array => [$audience->value => $audience->label()])
            ->all();
    }
}
