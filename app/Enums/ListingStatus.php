<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Published = 'published';
    case TakenDown = 'taken_down';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'Published',
            self::TakenDown => 'Taken down',
            self::Archived => 'Archived',
        };
    }

    /**
     * Only published listings reach the marketplace. A taken-down listing stays
     * visible to its owner so the seller can see why it was removed.
     */
    public function isVisibleToBuyers(): bool
    {
        return $this === self::Published;
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
