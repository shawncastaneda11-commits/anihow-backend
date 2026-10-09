<?php

namespace App\Http\Controllers\Api\Payments;

use App\Enums\OrderPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

        $orders = Order::query()
            ->where('farmer_seller_id', $request->user()->id)
            ->where('payment_status', $paymentStatus)
            ->with(['items', 'buyer', 'farm', 'latestProof.paymentQr'])
            ->latest()
            ->paginate();

        return OrderResource::collection($orders);
    }
}
