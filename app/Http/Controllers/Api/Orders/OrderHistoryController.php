<?php

namespace App\Http\Controllers\Api\Orders;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReceiptResource;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderHistoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reservation::class);

        $orders = $request->user()
            ->buyerReservations()
            ->whereIn('status', [
                ReservationStatus::Completed,
                ReservationStatus::Cancelled,
            ])
            ->with(['items', 'farmerSeller'])
            ->latest()
            ->paginate();

        return ReservationResource::collection($orders);
    }

    public function receipt(Request $request, Reservation $reservation): ReceiptResource
    {
        $this->authorize('view', $reservation);

        abort_unless($reservation->isOwnedByBuyer($request->user()), 403);

        $reservation->load(['items', 'farmerSeller']);

        return new ReceiptResource($reservation);
    }
}
