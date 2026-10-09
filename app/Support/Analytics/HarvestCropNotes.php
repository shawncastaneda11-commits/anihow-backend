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
        $unit = (string) ($crop['unit'] ?? '');

        $rejected = $crop['rejected_by_reason'] ?? [];

        if (is_object($rejected)) {
            $rejected = (array) $rejected;
        }

        $rejectedBits = [];

        foreach ($rejected as $reason => $quantity) {
            if ((float) $quantity == 0.0) {
                continue;
            }

            $label = HarvestRejectionReason::tryFrom((string) $reason)?->label() ?? (string) $reason;
            $rejectedBits[] = $label.' '.self::quantity((float) $quantity).' '.$unit;
        }

        if ($rejectedBits !== []) {
            $parts[] = 'Rejected: '.implode(', ', $rejectedBits);
        }

        $removedBits = [];

        foreach ($crop['removed_by_reason'] ?? [] as $reason => $quantity) {
            if ((float) $quantity == 0.0) {
                continue;
            }

            $label = StockRemovalReason::tryFrom((string) $reason)?->label() ?? (string) $reason;
            $removedBits[] = $label.' '.self::quantity((float) $quantity).' '.$unit;
        }

        if ($removedBits !== []) {
            $parts[] = 'Removed: '.implode(', ', $removedBits);
        }

        return implode(' · ', $parts);
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
