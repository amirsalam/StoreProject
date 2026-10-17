<?php

namespace App\Console\Commands;

use App\Domain\Dashboard\MetricsAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 *   php artisan metrics:rollup           # last 2 days
 *   php artisan metrics:rollup --days=7
 *   php artisan metrics:rollup --today   # today only (for the 15-min schedule)
 *
 * Schedules:
 *   Schedule::command('metrics:rollup --days=2')->dailyAt('02:00');
 *   Schedule::command('metrics:rollup --today')->everyFifteenMinutes();
 */
class MetricsRollup extends Command
{
    protected $signature = 'metrics:rollup
        {--days=2 : Recompute the last N days (inclusive of today)}
        {--today : Recompute today only}';

    protected $description = 'Roll OLTP data into daily_metrics for dashboard reads.';

    public function handle(MetricsAggregator $aggregator): int
    {
        $today = CarbonImmutable::today();

        [$from, $to] = $this->option('today')
            ? [$today, $today]
            : [$today->subDays(max(1, (int) $this->option('days')) - 1), $today];

        $started = microtime(true);
        $rows = $aggregator->rebuildRange($from, $to);
        $duration = round((microtime(true) - $started) * 1000);

        $this->info("Rolled {$rows} metric rows for {$from->toDateString()} → {$to->toDateString()} in {$duration}ms.");
        return self::SUCCESS;
    }
}
