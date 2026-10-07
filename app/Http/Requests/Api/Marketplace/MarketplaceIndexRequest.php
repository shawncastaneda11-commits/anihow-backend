<?php

namespace App\Http\Requests\Api\Marketplace;

use App\Enums\GrowingMethod;
use App\Enums\ProductCategory;
use App\Support\FarmPin;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MarketplaceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crop_type_id' => ['sometimes', 'integer', 'exists:crop_types,id'],
            'farm_id' => ['sometimes', 'integer', 'exists:farms,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['fair', 'freshest', 'price_asc', 'price_desc', 'availability', 'nearest'])],
            'mix_day' => ['sometimes', 'bail', 'date_format:Y-m-d', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    $fail('The mix day must be a date within the last 2 days or tomorrow.');

                    return;
                }

                $day = Carbon::parse($value, 'Asia/Manila')->startOfDay();
                $today = now()->timezone('Asia/Manila')->startOfDay();

                if ($day->lt($today->copy()->subDays(2)) || $day->gt($today->copy()->addDay())) {
                    $fail('The mix day must be a date within the last 2 days or tomorrow.');
                }
            }],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            ...FarmPin::nearRules(),
            'category' => ['sometimes', 'nullable', Rule::enum(ProductCategory::class)],
            'growing_method' => ['sometimes', 'nullable', Rule::enum(GrowingMethod::class)],
        ];
    }
}
