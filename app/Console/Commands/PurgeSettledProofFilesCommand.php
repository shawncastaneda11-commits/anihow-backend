<?php

namespace App\Console\Commands;

use App\Actions\Payments\PurgeSettledProofFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:purge-proof-files')]
#[Description('Delete payment screenshots from orders settled more than 90 days ago')]
class PurgeSettledProofFilesCommand extends Command
{
    public function handle(PurgeSettledProofFiles $purge): int
    {
        $deleted = $purge->handle();
        $this->info("Deleted {$deleted} payment screenshots.");

        return self::SUCCESS;
    }
}
