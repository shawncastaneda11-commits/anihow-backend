<?php

namespace App\Console\Commands;

use App\Actions\Orders\SweepStalePlacedOrdersAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:sweep-stale')]
#[Description('Remind sellers of stale placed orders, auto-cancel those left unanswered, and remind them to complete old confirmed ones')]
class SweepStaleOrdersCommand extends Command
{
    public function handle(SweepStalePlacedOrdersAction $action): int
    {
        $result = $action->handle();

        $this->info("Reminded {$result['reminded']}, cancelled {$result['cancelled']}, reminded to complete {$result['completion_reminded']}.");

        return self::SUCCESS;
    }
}
