<?php

namespace App\Console\Commands;

use App\Actions\Chat\CopyOrderMessagesToStallThreads;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('chat:copy-order-messages')]
#[Description('Copy order chat rows into the stall thread for each buyer and seller')]
class ChatCopyOrderMessages extends Command
{
    public function handle(CopyOrderMessagesToStallThreads $action): int
    {
        $copied = $action->handle();

        $this->info("Copied {$copied} order message(s) into stall threads.");

        return self::SUCCESS;
    }
}
