<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('announcements:notify-due')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('reservations:open-due')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('orders:sweep-stale')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping();
