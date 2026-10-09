<?php

namespace App\Http\Requests\Api\Reports;

use App\Enums\ReportReason;
use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Report::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $targetType = $this->input('target_type');

        return [
            'target_type' => ['required', 'string', Rule::in(['listing', 'review', 'order'])],
            'target_id' => [
                'required',
                'integer',
                Rule::when($targetType === 'listing', ['exists:listings,id']),
                Rule::when($targetType === 'review', ['exists:reviews,id']),
                Rule::when($targetType === 'order', ['exists:orders,id']),
            ],
            'reason' => [
                'required',
                Rule::enum(ReportReason::class),
                Rule::when(
                    $targetType === 'order',
                    Rule::in([ReportReason::PaymentProblem->value]),
                ),
            ],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function targetType(): string
    {
        return (string) $this->validated('target_type');
    }

    public function targetId(): int
    {
        return (int) $this->validated('target_id');
    }

    public function reason(): ReportReason
    {
        return ReportReason::from((string) $this->validated('reason'));
    }
}
