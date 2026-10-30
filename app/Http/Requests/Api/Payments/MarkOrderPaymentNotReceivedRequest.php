<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

class MarkOrderPaymentNotReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order !== null && ($this->user()?->can('advance', $order) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
