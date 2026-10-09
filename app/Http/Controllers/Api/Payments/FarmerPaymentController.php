<?php

namespace App\Http\Controllers\Api\Payments;

use App\Enums\OrderPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PaymentListItemResource;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class FarmerPaymentController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $paymentStatus = match ($request->query('status')) {
            'to_check' => OrderPaymentStatus::PaymentSent,
            'confirmed' => OrderPaymentStatus::Paid,
            'refund_due' => OrderPaymentStatus::RefundDue,
            default => null,
        };

        if ($paymentStatus === null) {
            throw ValidationException::withMessages([
                'status' => 'Choose to_check, confirmed, or refund_due.',
            ]);
        }

        $sellerId = $request->user()->id;

        $orders = Order::query()
            ->where('farmer_seller_id', $sellerId)
            ->where('payment_status', $paymentStatus)
            ->with(['items', 'buyer', 'latestProof.paymentQr'])
            ->get()
            ->map(fn (Order $order): array => $this->fromOrder($order));

        $reservations = Reservation::query()
            ->where('farmer_seller_id', $sellerId)
            ->where('payment_status', $paymentStatus)
            ->with(['buyer', 'latestProof.paymentQr'])
            ->get()
            ->map(fn (Reservation $reservation): array => $this->fromReservation($reservation));

        $items = $orders->concat($reservations)
            ->sortByDesc(fn (array $item): int => $item['sort_at'])
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;
        $slice = $items->forPage($page, $perPage)->map(function (array $item): array {
            unset($item['sort_at']);

            return $item;
        })->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return PaymentListItemResource::collection($paginator);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromOrder(Order $order): array
    {
        $names = $order->items->pluck('listing_name')->filter()->values()->all();
        $proof = $order->latestProof;
        $statusAt = $this->statusTime($order->payment_status, $proof, $order->paid_at, $order->cancelled_at);

        return [
            'kind' => 'order',
            'id' => $order->id,
            'buyer_name' => $order->buyer?->name,
            'title' => implode(', ', $names),
            'items' => $names,
            'amount' => (float) $order->total,
            'wallet' => $proof?->paymentQr?->wallet?->value,
            'reference' => $proof?->reference_number,
            'sent_at' => $proof?->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'status_at' => $statusAt?->toIso8601String(),
            'order_id' => $order->id,
            'reservation_id' => null,
            'sort_at' => $statusAt?->getTimestamp() ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromReservation(Reservation $reservation): array
    {
        $proof = $reservation->latestProof;
        $statusAt = $this->statusTime($reservation->payment_status, $proof, $reservation->paid_at, $reservation->cancelled_at);

        return [
            'kind' => 'reservation',
            'id' => $reservation->id,
            'buyer_name' => $reservation->buyer?->name,
            'title' => $reservation->listing_name,
            'items' => [$reservation->listing_name],
            'amount' => (float) $reservation->line_total,
            'wallet' => $proof?->paymentQr?->wallet?->value,
            'reference' => $proof?->reference_number,
            'sent_at' => $proof?->created_at?->toIso8601String(),
            'paid_at' => $reservation->paid_at?->toIso8601String(),
            'status_at' => $statusAt?->toIso8601String(),
            'order_id' => null,
            'reservation_id' => $reservation->id,
            'sort_at' => $statusAt?->getTimestamp() ?? 0,
        ];
    }

    private function statusTime(
        ?OrderPaymentStatus $status,
        ?PaymentProof $proof,
        mixed $paidAt,
        mixed $cancelledAt,
    ): mixed {
        return match ($status) {
            OrderPaymentStatus::PaymentSent => $proof?->created_at,
            OrderPaymentStatus::Paid => $paidAt,
            OrderPaymentStatus::RefundDue => $cancelledAt,
            default => $proof?->created_at ?? $paidAt ?? $cancelledAt,
        };
    }
}
