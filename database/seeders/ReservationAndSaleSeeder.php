<?php

namespace Database\Seeders;

use App\Actions\Pos\RecordPosSaleAction;
use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CompleteReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Actions\Reservations\MarkReservationReadyAction;
use App\Enums\ReservationActor;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReservationAndSaleSeeder extends Seeder
{
    public function run(): void
    {
        $ana = User::query()->where('email', 'ana.buyer@anihow.local')->firstOrFail();
        $ben = User::query()->where('email', 'ben.buyer@anihow.local')->firstOrFail();

        $juanTomato = Listing::query()->where('name', 'Tomato')->firstOrFail();
        $juanAmpalaya = Listing::query()->where('name', 'Ampalaya')->firstOrFail();
        $juanSitaw = Listing::query()->where('name', 'Sitaw')->firstOrFail();
        $mariaMango = Listing::query()->where('name', 'Carabao mango')->firstOrFail();
        $pedroKamote = Listing::query()->where('name', 'Kamote')->firstOrFail();

        $createReservation = app(CreateReservationAction::class);
        $markReady = app(MarkReservationReadyAction::class);
        $complete = app(CompleteReservationAction::class);
        $cancel = app(CancelReservationAction::class);
        $recordSale = app(RecordPosSaleAction::class);

        $ready = $createReservation->handle($ana, [
            ['listing_id' => $juanTomato->id, 'quantity' => 2],
            ['listing_id' => $juanAmpalaya->id, 'quantity' => 1],
        ], 'Pickup Saturday morning in San Francisco.');
        $markReady->handle($ready);

        $createReservation->handle($ana, [
            ['listing_id' => $mariaMango->id, 'quantity' => 2],
        ], 'Please set aside two ripe mangoes.');

        $done = $createReservation->handle($ben, [
            ['listing_id' => $pedroKamote->id, 'quantity' => 5],
        ], 'Will pick up after work.');
        $markReady->handle($done);
        $complete->handle($done);

        $toCancel = $createReservation->handle($ana, [
            ['listing_id' => $juanSitaw->id, 'quantity' => 3],
        ], 'Might cancel if rain is heavy.');
        $cancel->handle($toCancel, ReservationActor::Buyer, 'Changed plans.');

        $recordSale->handle(
            $juanTomato->farmerSeller,
            [['listing_id' => $juanTomato->id, 'quantity' => 3]],
            'Walk-in cash sale at the stall.',
        );

        $recordSale->handle(
            $pedroKamote->farmerSeller,
            [['listing_id' => $pedroKamote->id, 'quantity' => 2]],
            'Neighbor bought kamote at the farm gate.',
        );
    }
}
