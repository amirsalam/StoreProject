<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Billing\StripeWebhookProcessor;
use App\Domain\Payments\OrderPaymentProcessor;
use App\Domain\Payments\StripeCredentials;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Public webhook endpoint for Stripe → /webhooks/stripe.
 *
 * Verifies the signature against every secret an event may be signed
 * with (the store's gateway in Admin → Payment Gateways, and the
 * platform's .env secret), then routes each event to exactly one
 * processor — both write the webhook_events idempotency table:
 *   - one-time payment events (OrderPaymentProcessor::HANDLED_EVENTS)
 *     → queued ProcessPaymentWebhook → order paid → fulfillment;
 *   - everything else (subscriptions, invoices) → StripeWebhookProcessor.
 *
 * Returns 200 on every reachable path (except invalid signature) so
 * Stripe stops retrying — reconciliation jobs catch anything we
 * miss. Returning 500 on a worker-side bug would cause Stripe to
 * retry for hours and amplify whatever's wrong on our side.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookProcessor $billing, StripeCredentials $credentials): Response
    {
        $secrets = $credentials->webhookSecrets();
        if ($secrets === []) {
            // Hard fail on missing secret in production. In dev with
            // no Stripe configured, the route still exists but rejects
            // everything — safe.
            return response('webhook secret not configured', 503);
        }

        $event = null;
        foreach ($secrets as $secret) {
            try {
                $event = Webhook::constructEvent(
                    $request->getContent(),
                    (string) $request->header('Stripe-Signature', ''),
                    $secret,
                );
                break;
            } catch (SignatureVerificationException) {
                continue; // try the next secret
            } catch (\Throwable) {
                return response('invalid payload', 400);
            }
        }

        if (! $event instanceof Event) {
            return response('invalid signature', 400);
        }

        if (in_array($event->type, OrderPaymentProcessor::HANDLED_EVENTS, true)) {
            // Queued so Stripe gets its 200 in milliseconds; the job is
            // idempotent on the event id. On the sync driver it runs inline,
            // so a failure must not turn into a 500 either.
            try {
                ProcessPaymentWebhook::dispatch('stripe', $event->id, $event->type, $event->toArray());
            } catch (\Throwable $e) {
                report($e);
            }

            return response('ok', 200);
        }

        try {
            $billing->handle('stripe', $event->id, $event->type, $event->toArray());
        } catch (DuplicateWebhookException) {
            // Already seen — 200 so Stripe stops retrying.
        } catch (\Throwable $e) {
            report($e);
            // Still 200 — reconciliation handles whatever this lost.
        }

        return response('ok', 200);
    }
}
