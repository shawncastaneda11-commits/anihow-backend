<?php

namespace App\Console\Commands;

use App\Actions\Payments\RunPaymentUpkeep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:upkeep')]
#[Description('Expire unpaid orders and send payment reminders')]
class PaymentsUpkeepCommand extends Command
{
    public function handle(RunPaymentUpkeep $upkeep): int
    {
        $upkeep->handle();

        return self::SUCCESS;
    }
}
