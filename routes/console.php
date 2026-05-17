<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ai:jobs-recover-stuck')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('ai:queue-health --record')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('ai:technical-logs-cleanup --days=30')
    ->dailyAt('03:20')
    ->withoutOverlapping();

Schedule::command('google-ads:upload-offline-conversions --limit=50')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
