<?php

namespace App\Console\Commands;

use App\Actions\Testing\CreateTestFixtures;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('anihow:test-fixtures {--force} {--dry-run}')]
#[Description('Add the accounts and records the test instruments expect')]
class AnihowTestFixtures extends Command
{
    public function handle(CreateTestFixtures $fixtures): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $rows = $fixtures->handle((bool) $this->option('dry-run'));

        $this->table(
            ['Item', 'Status'],
            array_map(fn (array $row): array => [$row['item'], $row['status']], $rows),
        );

        return self::SUCCESS;
    }
}
