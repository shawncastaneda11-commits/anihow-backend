<?php

namespace App\Http\Controllers\Api\Reservations;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Enums\ReservationActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reservations\CancelReservationRequest;
use App\Http\Requests\Api\Reservations\StoreReservationRequest;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reservation::class);

        $reservations = $request->user()
            ->buyerReservations()
            ->with(['items', 'farmerSeller', 'review'])
            ->latest()
            ->paginate();

        return ReservationResource::collection($reservations);
    }

    public function store(StoreReservationRequest $request, CreateReservationAction $createReservation): JsonResponse
    {
        $reservation = $createReservation->handle(
            $request->user(),
            $request->items(),
            $request->validated('notes'),
        );

        return (new ReservationResource($reservation))
            ->additional(['message' => 'Reservation created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Reservation $reservation): ReservationResource
    {
        $this->authorize('view', $reservation);

        $reservation->load(['items', 'buyer', 'farmerSeller', 'review']);

        return new ReservationResource($reservation);
    }

    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        CancelReservationAction $cancelReservation,
    ): ReservationResource {
        $reservation = $cancelReservation->handle(
            $reservation,
            ReservationActor::Buyer,
            $request->validated('reason'),
        );

        return (new ReservationResource($reservation))
            ->additional(['message' => 'Reservation cancelled.']);
    }
}
