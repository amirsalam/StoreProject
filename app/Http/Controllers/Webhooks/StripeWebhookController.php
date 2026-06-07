<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Billing\StripeWebhookProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Public webhook endpoint for Stripe → /webhooks/stripe.
 *
 * Returns 200 on every reachable path (except invalid signature) so
 * Stripe stops retrying — reconciliation jobs catch anything we
 * miss. Returning 500 on a worker-side bug would cause Stripe to
 * retry for hours and amplify whatever's wrong on our side.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookProcessor $processor): Response
    {
        $secret = (string) config('services.stripe.webhook_secret', '');
        if ($secret === '') {
            // Hard fail on missing secret in production. In dev with
            // no Stripe configured, the route still exists but rejects
            // everything — safe.
            return response('webhook secret not configured', 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (SignatureVerificationException $e) {
            return response('invalid signature', 400);
        } catch (\Throwable $e) {
            return response('invalid payload', 400);
        }

        try {
            $processor->handle('stripe', $event->id, $event->type, $event->toArray());
        } catch (DuplicateWebhookException) {
            // Already seen — 200 so Stripe stops retrying.
        } catch (\Throwable $e) {
            report($e);
            // Still 200 — reconciliation handles whatever this lost.
        }

        return response('ok', 200);
    }
}
