<?php

namespace App\Enums;

enum SellerRequestStatus: string
{
    case Waiting = 'waiting';
    case Added = 'added';
    case NotAdded = 'not_added';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Added => 'Added',
            self::NotAdded => 'Not added',
        };
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
