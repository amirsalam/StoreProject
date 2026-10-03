<?php

namespace App\Jobs;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Payments\OrderPaymentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued wrapper around {@see OrderPaymentProcessor}.
 *
 * The webhook controller's sole responsibility is to verify the
 * signature, peel the event metadata, and dispatch this job. The actual
 * DB work happens off-thread so we can ack the gateway in milliseconds.
 *
 * Retry policy is exponential: 1m, 2m, 5m, 15m, 30m. After 5 failures
 * the job lands in failed_jobs; the reconciler will pick it up on its
 * next hourly run anyway.
 */
class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 900, 1800];

    public function __construct(
        public readonly string $gateway,
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly array $payload,
    ) {}

    public function handle(OrderPaymentProcessor $processor): void
    {
        try {
            $processor->handle($this->gateway, $this->eventId, $this->eventType, $this->payload);
        } catch (DuplicateWebhookException) {
            // Already processed — perfect, that's the whole point of
            // the idempotency gate. No retry, no error.
        }
    }

    /**
     * Deterministic job tag so we can fan out per-payment without
     * jobs for different payments fighting over the same worker.
     */
    public function uniqueId(): string
    {
        return $this->eventId;
    }
}
