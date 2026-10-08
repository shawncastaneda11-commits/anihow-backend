<?php

namespace App\Support;

use App\Enums\CostCategory;
use App\Enums\HarvestRejectionReason;
use Illuminate\Validation\Validator;

/**
 * Shared harvest input. quantity_good is always harvested minus rejected.
 * A breakdown, sent as a JSON object or as multipart cost_breakdown[seeds],
 * replaces production_cost with its sum.
 */
class HarvestInput
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'harvested_on' => [$presence, 'date', 'before_or_equal:today'],
            'quantity_harvested' => [$presence, 'numeric', 'gt:0', 'decimal:0,2', 'max:99999.99'],
            'quantity_rejected' => ['sometimes', 'numeric', 'gte:0', 'decimal:0,2', 'max:99999.99'],
            'rejection_reason' => ['nullable', 'string'],
            'rejection_note' => ['nullable', 'string', 'max:255'],
            'production_cost' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
            'cost_breakdown' => ['nullable', 'array'],
            'cost_breakdown.*' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
        ];
    }

    public static function prepare(array $input): array
    {
        $breakdown = $input['cost_breakdown'] ?? null;

        if (is_string($breakdown) && $breakdown !== '') {
            $decoded = json_decode($breakdown, true);
            if (is_array($decoded)) {
                $input['cost_breakdown'] = $decoded;
            }
        }

        if (array_key_exists('quantity_harvested', $input) && ! array_key_exists('quantity_rejected', $input)) {
            $input['quantity_rejected'] = 0;
        }

        return $input;
    }

    public static function check(Validator $validator, bool $valueAdded): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $data = $validator->getData();

        if (! self::present($data, 'quantity_harvested')) {
            return;
        }

        $harvested = self::scale($data['quantity_harvested']);
        $rejected = self::scale($data['quantity_rejected'] ?? 0);

        if (bccomp($rejected, $harvested, 2) === 1) {
            $validator->errors()->add(
                'quantity_rejected',
                'Rejected quantity cannot be more than the harvested quantity.',
            );
        }

        $reason = $data['rejection_reason'] ?? null;
        $reason = is_string($reason) && $reason !== '' ? $reason : null;

        if (bccomp($rejected, '0', 2) === 1) {
            $allowed = HarvestRejectionReason::valuesFor($valueAdded);

            if ($reason === null) {
                $validator->errors()->add('rejection_reason', 'Choose why this quantity was rejected.');
            } elseif (! in_array($reason, $allowed, true)) {
                $validator->errors()->add('rejection_reason', 'That reason does not apply to this crop.');
            } elseif ($reason === HarvestRejectionReason::Other->value && ! self::present($data, 'rejection_note')) {
                $validator->errors()->add('rejection_note', 'Add a note when the reason is Other.');
            }
        }

        $breakdown = self::breakdown($data['cost_breakdown'] ?? null, $validator);

        if ($breakdown === null || $validator->errors()->isNotEmpty()) {
            return;
        }

        $sum = self::sum($breakdown);
        if (! self::present($data, 'production_cost')) {
            return;
        }

        $sent = self::scale($data['production_cost']);
        $delta = bcsub($sent, $sum, 2);

        if (bccomp(ltrim($delta, '-'), '0.01', 2) === 1) {
            $validator->errors()->add(
                'production_cost',
                'The production cost does not match the breakdown.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     harvested_on: string,
     *     quantity_harvested: string,
     *     quantity_rejected: string,
     *     quantity_good: string,
     *     rejection_reason: ?string,
     *     rejection_note: ?string,
     *     production_cost: ?string,
     *     cost_breakdown: ?array<string, string>
     * }
     */
    public static function normalize(array $input): array
    {
        $harvested = self::scale($input['quantity_harvested']);
        $rejected = self::scale($input['quantity_rejected'] ?? 0);
        $good = bcsub($harvested, $rejected, 2);
        $breakdown = self::breakdown($input['cost_breakdown'] ?? null);
        $reason = bccomp($rejected, '0', 2) === 1 && self::present($input, 'rejection_reason')
            ? (string) $input['rejection_reason']
            : null;
        $note = $reason !== null && self::present($input, 'rejection_note')
            ? (string) $input['rejection_note']
            : null;

        $cost = null;
        if ($breakdown !== null) {
            $cost = self::sum($breakdown);
        } elseif (self::present($input, 'production_cost')) {
            $cost = self::scale($input['production_cost']);
        }

        return [
            'harvested_on' => (string) $input['harvested_on'],
            'quantity_harvested' => $harvested,
            'quantity_rejected' => $rejected,
            'quantity_good' => $good,
            'rejection_reason' => $reason,
            'rejection_note' => $note,
            'production_cost' => $cost,
            'cost_breakdown' => $breakdown,
        ];
    }

    public static function scale(mixed $value, int $scale = 2): string
    {
        $number = is_numeric($value) ? (string) $value : '0';

        return bcadd($number, '0', $scale);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function present(array $input, string $key): bool
    {
        if (! array_key_exists($key, $input) || $input[$key] === null) {
            return false;
        }

        return ! is_string($input[$key]) || trim($input[$key]) !== '';
    }

    /**
     * @return array<string, string>|null
     */
    private static function breakdown(mixed $raw, ?Validator $validator = null): ?array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (! is_array($raw)) {
            $validator?->errors()->add('cost_breakdown', 'The cost breakdown must be a set of amounts.');

            return null;
        }

        $allowed = array_map(fn (CostCategory $category): string => $category->value, CostCategory::cases());
        $breakdown = [];

        foreach ($raw as $category => $amount) {
            $key = (string) $category;

            if (! in_array($key, $allowed, true)) {
                $validator?->errors()->add('cost_breakdown', 'The cost breakdown has an unknown category.');

                return null;
            }

            if ($amount === null || $amount === '') {
                continue;
            }

            $breakdown[$key] = self::scale($amount);
        }

        return $breakdown === [] ? null : $breakdown;
    }

    /**
     * @param  array<string, string>  $breakdown
     */
    private static function sum(array $breakdown): string
    {
        $sum = '0.00';

        foreach ($breakdown as $amount) {
            $sum = bcadd($sum, $amount, 2);
        }

        return $sum;
    }
}
