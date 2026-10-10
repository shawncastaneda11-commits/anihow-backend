<?php

namespace App\Console\Commands;

use App\Actions\Testing\ResetAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('anihow:reset-accounts {--execute} {--force}')]
#[Description('Remove every account except Super Admins and every farm except the three client farms')]
class AnihowResetAccounts extends Command
{
    public function handle(ResetAccounts $reset): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $result = $reset->handle((bool) $this->option('execute'));

        if ($result['refused']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $countLabel = $result['executed'] ? 'deleted' : 'would delete';

        $this->table(
            ['Table', $countLabel],
            array_map(
                fn (array $row): array => [$row['table'], $row['count']],
                $result['rows'],
            ),
        );

        $this->newLine();
        $this->line('Kept Super Admins');
        $this->table(
            ['Email'],
            array_map(fn (string $email): array => [$email], $result['super_admins']),
        );

        $this->line('Kept farms');
        $this->table(
            ['Farm'],
            array_map(fn (string $name): array => [$name], $result['kept_farms']),
        );

        $this->line('Removed farms');
        $this->table(
            ['Farm'],
            array_map(fn (string $name): array => [$name], $result['removed_farms']),
        );

        return self::SUCCESS;
    }
}
