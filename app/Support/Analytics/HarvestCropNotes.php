<?php

namespace App\Support\Analytics;

use App\Enums\HarvestRejectionReason;
use App\Enums\StockRemovalReason;

/**
 * One harvest-by-crop notes cell, shared by the dashboard table and the yearly CSV.
 */
final class HarvestCropNotes
{
    /**
     * @param  array<string, mixed>  $crop
     */
    public static function format(array $crop): string
    {
        $parts = [];
        $rejected = self::rejected($crop);
        $removed = self::removed($crop);

        if ($rejected !== []) {
            $parts[] = 'Rejected: '.implode(', ', $rejected);
        }

        if ($removed !== []) {
            $parts[] = 'Removed: '.implode(', ', $removed);
        }

        return implode(' · ', $parts);
    }

    /**
     * Rejected stock by reason, such as "Spoiled 5 kg".
     *
     * @param  array<string, mixed>  $crop
     * @return list<string>
     */
    public static function rejected(array $crop): array
    {
        $rejected = $crop['rejected_by_reason'] ?? [];

        if (is_object($rejected)) {
            $rejected = (array) $rejected;
        }

        $bits = [];

        foreach ($rejected as $reason => $quantity) {
            if ((float) $quantity == 0.0) {
                continue;
            }

            $label = HarvestRejectionReason::tryFrom((string) $reason)?->label() ?? (string) $reason;
            $bits[] = $label.' '.self::amount((float) $quantity, (string) ($crop['unit'] ?? ''));
        }

        return $bits;
    }

    /**
     * Stock removed from the listing after harvest, by reason.
     *
     * @param  array<string, mixed>  $crop
     * @return list<string>
     */
    public static function removed(array $crop): array
    {
        $bits = [];

        foreach ($crop['removed_by_reason'] ?? [] as $reason => $quantity) {
            if ((float) $quantity == 0.0) {
                continue;
            }

            $label = StockRemovalReason::tryFrom((string) $reason)?->label() ?? (string) $reason;
            $bits[] = $label.' '.self::amount((float) $quantity, (string) ($crop['unit'] ?? ''));
        }

        return $bits;
    }

    /**
     * A quantity with its unit, plural where the unit is a counted thing: "2 bundles", "1.5 kg".
     */
    public static function amount(float $quantity, string $unit): string
    {
        $formatted = self::quantity($quantity);

        if ($unit === '' || in_array($unit, ['kg', 'g', 'ml', 'dozen'], true) || $formatted === '1') {
            return trim($formatted.' '.$unit);
        }

        return $formatted.' '.$unit.'s';
    }

    public static function quantity(float $quantity): string
    {
        $rounded = round($quantity, 2);
        $formatted = number_format($rounded, 2, '.', '');

        if (str_ends_with($formatted, '.00')) {
            return substr($formatted, 0, -3);
        }

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
