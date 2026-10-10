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
        $year = $request->validated('year');

        return self::fromValues(
            (string) $request->validated('range'),
            $request->validated('from') !== null ? (string) $request->validated('from') : null,
            $request->validated('to') !== null ? (string) $request->validated('to') : null,
            $year === null ? null : (int) $year,
            (string) ($request->validated('category') ?? 'all'),
        );
    }

    /**
     * Phone requests leave dates alone. The dashboard passes $clampToYear so a
     * typed range cannot run past today or longer than 366 days; a backwards one is swapped.
     */
    public static function fromValues(
        string $key,
        ?string $from,
        ?string $to,
        ?int $year,
        string $category,
        bool $clampToYear = false,
    ): self {
        $timezone = (string) config('app.timezone');

        if ($clampToYear && ! in_array($key, ['week', 'month', 'year', 'yearly', 'custom'], true)) {
            $key = 'month';
        }

        if ($clampToYear && $key === 'yearly') {
            $currentYear = (int) now()->year;

            if ($year === null || $year < 2020 || $year > $currentYear) {
                $year = $currentYear;
            }
        }

        if ($clampToYear && $key === 'custom' && ($from === null || $from === '' || $to === null || $to === '')) {
            $key = 'month';
        }

        [$start, $end] = match ($key) {
            'week' => [now()->startOfWeek()->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth()->startOfDay(), now()->endOfDay()],
            'year' => [now()->startOfYear()->startOfDay(), now()->endOfDay()],
            'yearly' => [
                Carbon::create((int) $year, 1, 1, 0, 0, 0, $timezone)->startOfDay(),
                Carbon::create((int) $year, 12, 31, 0, 0, 0, $timezone)->endOfDay(),
            ],
            default => [
                Carbon::createFromFormat('!Y-m-d', (string) $from, $timezone)->startOfDay(),
                Carbon::createFromFormat('!Y-m-d', (string) $to, $timezone)->endOfDay(),
            ],
        };

        if ($clampToYear && $key === 'custom') {
            $today = now()->endOfDay();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            if ($end->greaterThan($today)) {
                $end = $today;
            }

            if ($start->greaterThan($end)) {
                $start = $end->copy()->startOfDay();
            }

            if (self::inclusiveDays($start, $end) > 366) {
                $start = $end->copy()->startOfDay()->subDays(365);
            }
        }

        $grouping = $key === 'yearly' ? 'month' : self::groupingFor($start, $end);

        return new self($key, $start, $end, $grouping, $category, $key === 'yearly' ? $year : null);
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
