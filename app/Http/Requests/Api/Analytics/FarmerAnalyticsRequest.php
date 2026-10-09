<?php

namespace App\Http\Requests\Api\Analytics;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class FarmerAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ViewOwnAnalytics->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'in:week,month'],
            'range' => ['sometimes', 'in:week,month,year,custom,yearly'],
            'from' => ['required_if:range,custom', 'date_format:Y-m-d'],
            'to' => ['required_if:range,custom', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today', $this->spanAtMostAYear()],
            'year' => ['required_if:range,yearly', 'integer', 'between:2020,'.now()->year],
            'category' => ['sometimes', 'in:all,fresh,value_added'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'period' => $this->query('period', 'week'),
        ]);
    }

    private function spanAtMostAYear(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $from = $this->input('from');

            if (! is_string($from) || ! is_string($value)) {
                return;
            }

            try {
                $start = Carbon::createFromFormat('!Y-m-d', $from, (string) config('app.timezone'));
                $end = Carbon::createFromFormat('!Y-m-d', $value, (string) config('app.timezone'));
            } catch (\Throwable) {
                return;
            }

            if ($start === false || $end === false || $start === null || $end === null) {
                return;
            }

            $days = (int) $start->startOfDay()->diff($end->copy()->startOfDay())->days + 1;

            if ($days > 366) {
                $fail('Pick at most 366 days.');
            }
        };
    }
}
