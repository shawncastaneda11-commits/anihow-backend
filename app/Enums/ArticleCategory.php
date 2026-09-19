<?php

namespace App\Enums;

enum ArticleCategory: string
{
    case CropCare = 'crop_care';
    case PestManagement = 'pest_management';

    public function label(): string
    {
        return match ($this) {
            self::CropCare => 'Crop care',
            self::PestManagement => 'Pest management',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category): array => [$category->value => $category->label()])
            ->all();
    }
}
