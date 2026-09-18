<?php

namespace Database\Seeders;

use App\Actions\Favorites\AddFavoriteAction;
use App\Actions\Pos\RecordPosSaleAction;
use App\Actions\Reviews\CreateReviewAction;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrustAndUsabilitySeeder extends Seeder
{
    public function run(): void
    {
        $ana = User::query()->where('email', 'ana.buyer@anihow.local')->firstOrFail();
        $ben = User::query()->where('email', 'ben.buyer@anihow.local')->firstOrFail();

        $tomato = Listing::query()->where('name', 'Tomato')->firstOrFail();
        $mango = Listing::query()->where('name', 'Carabao mango')->firstOrFail();
        $ampalaya = Listing::query()->where('name', 'Ampalaya')->firstOrFail();

        $addFavorite = app(AddFavoriteAction::class);
        $addFavorite->handle($ana, $tomato);
        $addFavorite->handle($ana, $mango);

        $completed = Reservation::query()
            ->where('buyer_id', $ben->id)
            ->where('status', ReservationStatus::Completed)
            ->firstOrFail();

        app(CreateReviewAction::class)->handle(
            $ben,
            $completed,
            5,
            'Sweet kamote, easy pickup in Navarro.',
        );

        app(RecordPosSaleAction::class)->handle(
            $ampalaya->farmerSeller,
            [['listing_id' => $ampalaya->id, 'quantity' => 20]],
            'Bulk walk-in after the morning harvest.',
        );

        $ana->inAppNotifications()
            ->whereNull('read_at')
            ->orderBy('id')
            ->first()
            ?->markRead();
    }
}
