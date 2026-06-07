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
