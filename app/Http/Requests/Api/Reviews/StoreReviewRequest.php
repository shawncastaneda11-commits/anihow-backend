<?php

namespace App\Http\Requests\Api\Reviews;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    private ?Order $resolvedOrder = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Review::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * A review unlocks at Completed, one per order, and only for the buyer on
     * that order. All three conditions are checked here so the controller
     * cannot be reached with a bad order.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $order = Order::with('review')->find($this->validated('order_id'));

                if ($order === null) {
                    return;
                }

                if (! $this->user()->can('createForOrder', [Review::class, $order])) {
                    $validator->errors()->add(
                        'order_id',
                        'You can only review your own completed orders, once each.',
                    );

                    return;
                }

                $this->resolvedOrder = $order;
            },
        ];
    }

    public function order(): Order
    {
        return $this->resolvedOrder ??= Order::findOrFail($this->validated('order_id'));
    }
}
