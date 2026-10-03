<?php

namespace App\Http\Requests\Api\Analytics;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'period' => $this->query('period', 'week'),
        ]);
    }
}
