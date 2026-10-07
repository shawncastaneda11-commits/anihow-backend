<?php

namespace App\Console\Commands;

use App\Actions\Reservations\OpenDueReservations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:open-due')]
#[Description('Convert or cancel reservations whose harvest window has opened')]
class OpenDueReservationsCommand extends Command
{
    public function handle(OpenDueReservations $openDue): int
    {
        $openDue->handle();

        return self::SUCCESS;
    }
}
