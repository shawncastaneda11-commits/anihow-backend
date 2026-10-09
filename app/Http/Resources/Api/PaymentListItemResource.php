<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row in the seller payment queue. An order and a reservation share this shape.
 *
 * @mixin array<string, mixed>
 */
class PaymentListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->resource['kind'],
            'id' => $this->resource['id'],
            'buyer_name' => $this->resource['buyer_name'],
            'title' => $this->resource['title'],
            'items' => $this->resource['items'],
            'amount' => $this->resource['amount'],
            'wallet' => $this->resource['wallet'],
            'reference' => $this->resource['reference'],
            'sent_at' => $this->resource['sent_at'],
            'paid_at' => $this->resource['paid_at'],
            'status_at' => $this->resource['status_at'],
            'order_id' => $this->resource['order_id'],
            'reservation_id' => $this->resource['reservation_id'],
        ];
    }
}
