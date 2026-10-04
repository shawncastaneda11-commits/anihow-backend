<?php

namespace App\Enums;

enum ProductCategory: string
{
    case FreshProduce = 'fresh_produce';
    case ValueAdded = 'value_added';

    public function label(): string
    {
        return match ($this) {
            self::FreshProduce => 'Fresh produce',
            self::ValueAdded => 'Value-added',
        };
    }

    public function labelFil(): string
    {
        return match ($this) {
            self::FreshProduce => 'Sariwang Ani',
            self::ValueAdded => 'Prosesong Produkto',
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
