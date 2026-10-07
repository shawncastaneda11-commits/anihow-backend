<?php

namespace App\Enums;

enum ReportReason: string
{
    case WrongOrMisleading = 'wrong_or_misleading';
    case ProhibitedItem = 'prohibited_item';
    case OffensiveContent = 'offensive_content';
    case SpamOrFake = 'spam_or_fake';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WrongOrMisleading => 'Wrong or misleading information',
            self::ProhibitedItem => 'Item not allowed to be sold',
            self::OffensiveContent => 'Offensive or abusive content',
            self::SpamOrFake => 'Spam or fake',
            self::Other => 'Other',
        };
    }

    public function labelFilipino(): string
    {
        return match ($this) {
            self::WrongOrMisleading => 'Mali o mapanlinlang na impormasyon',
            self::ProhibitedItem => 'Bawal ibenta ang item na ito',
            self::OffensiveContent => 'Masakit o mapang-abusong nilalaman',
            self::SpamOrFake => 'Spam o peke',
            self::Other => 'Iba pa',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason): array => [$reason->value => $reason->label()])
            ->all();
    }
}
