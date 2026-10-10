<?php

namespace Database\Seeders;

use App\Actions\Testing\CreateDemoData;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public const PASSWORD = CreateDemoData::PASSWORD;

    public const EMAIL_DOMAIN = CreateDemoData::EMAIL_DOMAIN;

    public function run(): void
    {
        if (! $this->mayRun()) {
            $this->command?->warn('DemoSeeder skipped: not local/testing and not invoked with --class=DemoSeeder.');

            return;
        }

        $rows = app(CreateDemoData::class)->handle();

        $this->command?->table(['Metric', 'Value'], $rows);
    }

    private function mayRun(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        $argv = $_SERVER['argv'] ?? [];

        return collect($argv)->contains(
            fn (mixed $arg): bool => is_string($arg) && str_contains($arg, 'DemoSeeder'),
        );
    }
}
