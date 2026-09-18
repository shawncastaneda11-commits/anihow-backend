<?php

namespace App\Http\Controllers\Api\Reservations;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CompleteReservationAction;
use App\Actions\Reservations\MarkReservationReadyAction;
use App\Enums\ReservationActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reservations\CancelReservationRequest;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reservation::class);

        $reservations = $request->user()
            ->incomingReservations()
            ->with(['items', 'buyer'])
            ->latest()
            ->paginate();

        return ReservationResource::collection($reservations);
    }

    public function show(Reservation $reservation): ReservationResource
    {
        $this->authorize('view', $reservation);

        $reservation->load(['items', 'buyer', 'farmerSeller']);

        return new ReservationResource($reservation);
    }

    public function ready(Reservation $reservation, MarkReservationReadyAction $markReady): ReservationResource
    {
        $this->authorize('manageAsFarmer', $reservation);

        $reservation = $markReady->handle($reservation);

        return (new ReservationResource($reservation))
            ->additional(['message' => 'Reservation marked ready for pickup.']);
    }

    public function complete(Reservation $reservation, CompleteReservationAction $completeReservation): ReservationResource
    {
        $this->authorize('manageAsFarmer', $reservation);

        $reservation = $completeReservation->handle($reservation);

        return (new ReservationResource($reservation))
            ->additional(['message' => 'Reservation completed.']);
    }

    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        CancelReservationAction $cancelReservation,
    ): ReservationResource {
        $reservation = $cancelReservation->handle(
            $reservation,
            ReservationActor::FarmerSeller,
            $request->validated('reason'),
        );

        return (new ReservationResource($reservation))
            ->additional(['message' => 'Reservation cancelled.']);
    }
}
