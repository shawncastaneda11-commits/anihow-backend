<?php

namespace App\Console\Commands;

use App\Actions\Orders\SweepStalePlacedOrdersAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:sweep-stale')]
#[Description('Remind sellers of stale placed orders and auto-cancel those left unanswered')]
class SweepStaleOrdersCommand extends Command
{
    public function handle(SweepStalePlacedOrdersAction $action): int
    {
        $result = $action->handle();

        $this->info("Reminded {$result['reminded']}, cancelled {$result['cancelled']}.");

        return self::SUCCESS;
    }
}
