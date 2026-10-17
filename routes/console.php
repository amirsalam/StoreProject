<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Payment reconciliation runs hourly. ->withoutOverlapping locks for
 * 60 minutes so a slow run can't double-up with the next tick.
 */
Schedule::command('reconcile:payments')
    ->hourly()
    ->withoutOverlapping(60)
    ->runInBackground();

/**
 * Dashboard rollups.
 *   - Full nightly catch-up at 02:00 (recomputes the last 2 days
 *     idempotently — catches anything that landed late).
 *   - 15-min "today" pass so vendor dashboards stay fresh during the day.
 */
Schedule::command('metrics:rollup --days=2')
    ->dailyAt('02:00')
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('metrics:rollup --today')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->runInBackground();

/**
 * Installation license check-in. Runs hourly but only contacts the license
 * server when a check-in is due (≈ daily; hourly retries after a failure).
 * The minute comes from APP_KEY so installations don't all call at once.
 * Licensed-area requests also trigger due check-ins, for hosts without cron.
 */
Schedule::command('app-license:verify --if-due')
    ->cron(sprintf('%d * * * *', crc32((string) config('app.key')) % 60))
    ->withoutOverlapping(10)
    ->runInBackground();
