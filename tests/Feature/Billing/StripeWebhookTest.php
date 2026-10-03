<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Billing\StripeWebhookProcessor;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\WebhookEvent;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stripe webhook processor — idempotency + state transitions.
 *
 * Driven through the processor directly (not the HTTP endpoint) to
 * skip Stripe's signature verification, which is what the endpoint
 * does ON TOP of the logic these tests cover. The endpoint itself
 * is just signature-check + delegate.
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
    }

    public function test_subscription_created_event_creates_local_subscription(): void
    {
        $tenant = $this->makeTenant();

        $this->processEvent(
            id: 'evt_001',
            type: 'customer.subscription.created',
            object: $this->subObject('sub_001', $tenant->id, 'active'),
        );

        $sub = TenantSubscription::query()->where('stripe_subscription_id', 'sub_001')->first();
        $this->assertNotNull($sub);
        $this->assertSame($tenant->id, $sub->tenant_id);
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame(Plan::SLUG_PRO, $sub->plan->slug);

        // Webhook event was logged + marked processed.
        $event = WebhookEvent::query()->where('gateway_event_id', 'evt_001')->first();
        $this->assertNotNull($event->processed_at);
    }

    public function test_duplicate_event_id_is_a_noop(): void
    {
        $tenant = $this->makeTenant();

        $this->processEvent(
            id: 'evt_dup',
            type: 'customer.subscription.created',
            object: $this->subObject('sub_002', $tenant->id, 'active'),
        );
        $this->assertSame(1, WebhookEvent::query()->where('gateway_event_id', 'evt_dup')->count());

        try {
            $this->processEvent(
                id: 'evt_dup',                                  // SAME id — should be caught
                type: 'customer.subscription.created',
                object: $this->subObject('sub_002', $tenant->id, 'active'),
            );
            $this->fail('Expected DuplicateWebhookException');
        } catch (DuplicateWebhookException $e) {
            // expected — duplicate insert hit the unique index
        }

        $this->assertSame(1, WebhookEvent::query()->where('gateway_event_id', 'evt_dup')->count());
        $this->assertSame(1, TenantSubscription::query()->where('stripe_subscription_id', 'sub_002')->count());
    }

    public function test_subscription_updated_overwrites_plan_and_period(): void
    {
        $tenant = $this->makeTenant();

        // Initial: pro monthly
        $this->processEvent(
            id: 'evt_010',
            type: 'customer.subscription.created',
            object: $this->subObject('sub_010', $tenant->id, 'active', plan: Plan::SLUG_PRO),
        );

        // Upgrade: business annual, longer period
        $this->processEvent(
            id: 'evt_011',
            type: 'customer.subscription.updated',
            object: array_merge(
                $this->subObject('sub_010', $tenant->id, 'active', plan: Plan::SLUG_BUSINESS),
                [
                    'current_period_end' => now()->addYear()->timestamp,
                    'metadata' => [
                        'tenant_id' => (string) $tenant->id,
                        'plan_slug' => Plan::SLUG_BUSINESS,
                        'billing_cycle' => TenantSubscription::CYCLE_ANNUAL,
                    ],
                ],
            ),
        );

        $sub = TenantSubscription::query()->where('stripe_subscription_id', 'sub_010')->first();
        $this->assertSame(Plan::SLUG_BUSINESS, $sub->plan->slug);
        $this->assertSame(TenantSubscription::CYCLE_ANNUAL, $sub->billing_cycle);
    }

    public function test_payment_failed_marks_subscription_past_due(): void
    {
        $tenant = $this->makeTenant();
        $this->processEvent(
            id: 'evt_020',
            type: 'customer.subscription.created',
            object: $this->subObject('sub_020', $tenant->id, 'active'),
        );

        $this->processEvent(
            id: 'evt_021',
            type: 'invoice.payment_failed',
            object: ['subscription' => 'sub_020'],
        );

        $sub = TenantSubscription::query()->where('stripe_subscription_id', 'sub_020')->first();
        $this->assertSame(TenantSubscription::STATUS_PAST_DUE, $sub->status);
    }

    public function test_invoice_paid_clears_past_due(): void
    {
        $tenant = $this->makeTenant();
        $this->processEvent(
            id: 'evt_030',
            type: 'customer.subscription.created',
            object: $this->subObject('sub_030', $tenant->id, 'past_due'),
        );

        $this->processEvent(
            id: 'evt_031',
            type: 'invoice.paid',
            object: ['subscription' => 'sub_030'],
        );

        $sub = TenantSubscription::query()->where('stripe_subscription_id', 'sub_030')->first();
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $sub->status);
    }

    public function test_subscription_deleted_cancels_local_subscription(): void
    {
        $tenant = $this->makeTenant();
        $this->processEvent(
            id: 'evt_040',
            type: 'customer.subscription.created',
            object: $this->subObject('sub_040', $tenant->id, 'active'),
        );

        $this->processEvent(
            id: 'evt_041',
            type: 'customer.subscription.deleted',
            object: ['id' => 'sub_040'],
        );

        $sub = TenantSubscription::query()->where('stripe_subscription_id', 'sub_040')->first();
        $this->assertSame(TenantSubscription::STATUS_CANCELLED, $sub->status);
        $this->assertNotNull($sub->cancelled_at);
    }

    public function test_unknown_event_type_is_logged_but_does_not_throw(): void
    {
        $this->processEvent(
            id: 'evt_050',
            type: 'charge.succeeded',                            // unsupported here
            object: ['id' => 'ch_050'],
        );

        $event = WebhookEvent::query()->where('gateway_event_id', 'evt_050')->first();
        $this->assertNotNull($event);
        $this->assertNotNull($event->processed_at);              // still marked processed
        $this->assertNull($event->processing_error);
    }

    private function makeTenant(): Tenant
    {
        $owner = User::factory()->create();

        return Tenant::factory()->create(['owner_id' => $owner->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function subObject(string $stripeSubId, int $tenantId, string $status, string $plan = Plan::SLUG_PRO): array
    {
        return [
            'id' => $stripeSubId,
            'status' => $status,
            'customer' => 'cus_test',
            'current_period_start' => now()->subDay()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'trial_end' => null,
            'cancel_at' => null,
            'canceled_at' => null,
            'metadata' => [
                'tenant_id' => (string) $tenantId,
                'plan_slug' => $plan,
                'billing_cycle' => TenantSubscription::CYCLE_MONTHLY,
            ],
        ];
    }

    private function processEvent(string $id, string $type, array $object): void
    {
        app(StripeWebhookProcessor::class)->handle('stripe', $id, $type, [
            'data' => ['object' => $object],
        ]);
    }
}
