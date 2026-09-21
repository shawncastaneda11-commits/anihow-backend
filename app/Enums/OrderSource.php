<?php

namespace App\Enums;

/**
 * How an order entered the ledger. Both land in the same orders table and
 * both are read by the same descriptive summaries.
 *
 *   App     placed by a buyer through checkout, starting at Placed.
 *   WalkIn  recorded by the farmer-seller after an in-person handover with
 *           someone who has no buyer account, created directly at Completed.
 *
 * A second way into one ledger, not a second sales surface. Decision 19.
 */
enum OrderSource: string
{
    case App = 'app';
    case WalkIn = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::App => 'App order',
            self::WalkIn => 'Walk-in sale',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $source): array => [$source->value => $source->label()])
            ->all();
    }
}
