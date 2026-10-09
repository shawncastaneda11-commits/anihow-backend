<?php

namespace App\Support\Analytics;

use App\Http\Requests\Api\Analytics\FarmerAnalyticsRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A farmer analytics window. Dates are Asia/Manila because that is the app timezone.
 * from is the start of the first day and to is the end of the last day.
 */
final readonly class AnalyticsRange
{
    public function __construct(
        public string $key,
        public CarbonInterface $from,
        public CarbonInterface $to,
        public string $grouping,
        public string $category,
        public ?int $year = null,
    ) {}

    public static function fromRequest(FarmerAnalyticsRequest $request): self
    {
        $key = (string) $request->validated('range');
        $category = (string) ($request->validated('category') ?? 'all');
        $year = $request->validated('year');
        $year = $year === null ? null : (int) $year;
        $timezone = (string) config('app.timezone');

        [$from, $to] = match ($key) {
            'week' => [now()->startOfWeek()->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth()->startOfDay(), now()->endOfDay()],
            'year' => [now()->startOfYear()->startOfDay(), now()->endOfDay()],
            'yearly' => [
                Carbon::create((int) $year, 1, 1, 0, 0, 0, $timezone)->startOfDay(),
                Carbon::create((int) $year, 12, 31, 0, 0, 0, $timezone)->endOfDay(),
            ],
            default => [
                Carbon::createFromFormat('!Y-m-d', (string) $request->validated('from'), $timezone)->startOfDay(),
                Carbon::createFromFormat('!Y-m-d', (string) $request->validated('to'), $timezone)->endOfDay(),
            ],
        };

        $grouping = $key === 'yearly' ? 'month' : self::groupingFor($from, $to);

        return new self($key, $from, $to, $grouping, $category, $key === 'yearly' ? $year : null);
    }

    public static function inclusiveDays(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) $from->copy()->startOfDay()->diff($to->copy()->startOfDay())->days + 1;
    }

    public static function groupingFor(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = self::inclusiveDays($from, $to);

        if ($days <= 31) {
            return 'day';
        }

        if ($days <= 184) {
            return 'week';
        }

        return 'month';
    }

    public function periodKey(CarbonInterface $at): string
    {
        return match ($this->grouping) {
            'month' => $at->format('Y-m'),
            'week' => $at->format('o-\WW'),
            default => $at->format('Y-m-d'),
        };
    }

    /**
     * @return list<array{key: string, start: string, end: string, future: bool}>
     */
    public function buckets(): array
    {
        $from = $this->from->copy()->startOfDay();
        $to = $this->to->copy()->startOfDay();
        $buckets = [];

        if ($this->grouping === 'day') {
            $cursor = $from->copy();

            while ($cursor->lte($to)) {
                $key = $cursor->toDateString();
                $buckets[] = [
                    'key' => $key,
                    'start' => $key,
                    'end' => $key,
                    'future' => false,
                ];
                $cursor->addDay();
            }

            return $buckets;
        }

        if ($this->grouping === 'week') {
            $cursor = $from->copy()->startOfWeek();

            while ($cursor->lte($to)) {
                $weekEnd = $cursor->copy()->endOfWeek()->startOfDay();
                $start = $cursor->lt($from) ? $from->copy() : $cursor->copy();
                $end = $weekEnd->gt($to) ? $to->copy() : $weekEnd;
                $buckets[] = [
                    'key' => $this->periodKey($cursor),
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'future' => false,
                ];
                $cursor->addWeek();
            }

            return $buckets;
        }

        $cursor = $from->copy()->startOfMonth();
        $currentMonth = now()->format('Y-m');

        while ($cursor->lte($to)) {
            $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
            $start = $cursor->lt($from) ? $from->copy() : $cursor->copy();
            $end = $monthEnd->gt($to) ? $to->copy() : $monthEnd;
            $key = $cursor->format('Y-m');
            $buckets[] = [
                'key' => $key,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'future' => $this->key === 'yearly'
                    && $this->year === (int) now()->year
                    && $key > $currentMonth,
            ];
            $cursor->addMonth();
        }

        return $buckets;
    }
}
