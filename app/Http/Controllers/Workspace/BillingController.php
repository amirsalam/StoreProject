<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Billing\BillingService;
use App\Domain\Payments\OrderPaymentProcessor;
use App\Domain\Plans\PlanGate;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\TenantSubscription;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request, PlanGate $gate): Response
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 404);

        $canManagePayments = $tenant->canManagePayments($request->user());
        $gateway = $canManagePayments
            ? PaymentGateway::query()->where('provider', 'stripe')->first()
            : null;

        $subscription = $tenant->currentSubscription;
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'description', 'monthly_cents', 'annual_cents', 'limits', 'features']);

        return Inertia::render('workspace/billing/index', [
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'current_period_end' => $subscription->current_period_end,
                'trial_ends_at' => $subscription->trial_ends_at,
                'cancel_at' => $subscription->cancel_at,
                'plan' => [
                    'slug' => $subscription->plan?->slug,
                    'name' => $subscription->plan?->name,
                ],
            ] : null,
            'plans' => $plans,
            'usage' => $gate->snapshot($tenant),
            'is_stripe_configured' => $this->stripeConfigured(),
            'can_manage_payments' => $canManagePayments,
            // Never the secret values — only whether they are set. The
            // publishable key is public by design, so it is shown to confirm
            // which account is connected.
            'payment_gateway' => $gateway ? [
                'environment' => $gateway->environment,
                'is_active' => $gateway->is_active,
                'publishable_key' => $gateway->credentials['publishable_key'] ?? null,
                'has_secret_key' => filled($gateway->credentials['secret_key'] ?? null),
                'has_webhook_secret' => filled($gateway->getRawOriginal('webhook_secret')),
                'last_connection_at' => $gateway->last_connection_at,
            ] : null,
            'stripe_webhook' => $canManagePayments ? [
                'url' => route('webhooks.stripe'),
                'events' => OrderPaymentProcessor::HANDLED_EVENTS,
            ] : null,
        ]);
    }

    public function changePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', [Plan::SLUG_PRO, Plan::SLUG_BUSINESS])],
            'cycle' => ['required', 'in:'.implode(',', [TenantSubscription::CYCLE_MONTHLY, TenantSubscription::CYCLE_ANNUAL])],
        ]);

        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 404);

        if (! $this->stripeConfigured()) {
            return back()->with('error', 'Stripe is not configured. Set STRIPE_SECRET + the price ids in your .env.');
        }

        try {
            $url = $this->billing->checkoutSession(
                tenant: $tenant,
                planSlug: $data['plan'],
                cycle: $data['cycle'],
                successUrl: route('workspace.billing.index').'?upgraded=1',
                cancelUrl: route('workspace.billing.index'),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not start checkout. Try again or contact support.');
        }

        return redirect()->away($url);
    }

    public function portal(): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 404);

        if (! $this->stripeConfigured()) {
            return back()->with('error', 'Stripe is not configured.');
        }

        try {
            $url = $this->billing->portalSession(
                $tenant,
                returnUrl: route('workspace.billing.index'),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not open billing portal. Try again or contact support.');
        }

        return redirect()->away($url);
    }

    public function cancel(): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 404);

        if (! $this->stripeConfigured()) {
            return back()->with('error', 'Stripe is not configured.');
        }

        try {
            $this->billing->cancelAtPeriodEnd($tenant);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not schedule cancellation.');
        }

        return back()->with('success', 'Subscription will cancel at the end of the current period.');
    }

    private function stripeConfigured(): bool
    {
        return ! empty(config('services.stripe.secret'));
    }
}
