<?php

namespace App\Domain\Status;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Live health of the platform's moving parts, behind the public /status
 * page and the footer's status line.
 *
 * Each component is actually exercised (a query, a cache round-trip, a
 * look at the queue backlog) rather than assumed. Failures are reported
 * to the log but never exposed publicly — the page only says which
 * component is degraded. The snapshot is cached for a minute so the
 * footer can show it on every page without re-running the checks.
 */
class SystemStatus
{
    public const OPERATIONAL = 'operational';

    public const DEGRADED = 'degraded';

    public const CACHE_SECONDS = 60;

    /** A pending job this old means no worker is draining the queue. */
    public const QUEUE_BACKLOG_SECONDS = 600;

    private const CACHE_KEY = 'system-status:snapshot';

    /**
     * @return array{overall: string, checked_at: string, components: list<array{key: string, status: string, latency_ms: int}>}
     */
    public function current(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->check());
        } catch (Throwable) {
            // The cache itself is failing: check live instead of failing the page.
            return $this->check();
        }
    }

    /**
     * @return array{overall: string, checked_at: string, components: list<array{key: string, status: string, latency_ms: int}>}
     */
    public function check(): array
    {
        $components = [
            $this->probe('database', fn () => DB::select('select 1')),
            $this->probe('cache', function () {
                $value = Str::random(16);
                Cache::put('system-status:probe', $value, 10);
                if (Cache::get('system-status:probe') !== $value) {
                    throw new RuntimeException('Cache read-back did not match the value written.');
                }
            }),
            $this->probe('queue', fn () => $this->checkQueue()),
        ];

        $healthy = collect($components)->every(fn (array $c) => $c['status'] === self::OPERATIONAL);

        return [
            'overall' => $healthy ? self::OPERATIONAL : self::DEGRADED,
            'checked_at' => now()->toIso8601String(),
            'components' => $components,
        ];
    }

    /**
     * @return array{key: string, status: string, latency_ms: int}
     */
    private function probe(string $key, Closure $check): array
    {
        $started = hrtime(true);

        try {
            $check();
            $status = self::OPERATIONAL;
        } catch (Throwable $e) {
            report($e);
            $status = self::DEGRADED;
        }

        return [
            'key' => $key,
            'status' => $status,
            'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
        ];
    }

    private function checkQueue(): void
    {
        $connection = (string) config('queue.default');

        if (config("queue.connections.{$connection}.driver") !== 'database') {
            // Reaching the backend is all we can check generically.
            Queue::connection($connection)->size();

            return;
        }

        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $oldest = DB::table($table)->whereNull('reserved_at')->min('available_at');

        if ($oldest !== null && now()->getTimestamp() - (int) $oldest > self::QUEUE_BACKLOG_SECONDS) {
            throw new RuntimeException('Queue backlog: pending jobs are not being processed.');
        }
    }
}
