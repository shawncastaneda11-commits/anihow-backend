<?php

namespace App\Console\Commands;

use App\Actions\Testing\CreateDemoData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('anihow:demo-data {--weeks=26} {--force}')]
#[Description('Build demo farms, accounts, and order history for the client farms')]
class AnihowDemoData extends Command
{
    public function handle(CreateDemoData $demo): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $rows = $demo->handle(max(1, (int) $this->option('weeks')));

        $this->table(['Metric', 'Value'], $rows);

        return self::SUCCESS;
    }
}
