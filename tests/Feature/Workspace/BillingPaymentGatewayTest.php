<?php

namespace Tests\Feature\Workspace;

use App\Domain\Billing\StripeGateway;
use App\Domain\Payments\StripeCredentials;
use App\Models\PaymentGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Workspace → Billing lets the workspace owner/admin configure the Stripe
 * gateway their store checkout charges with — no .env involved.
 */
class BillingPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.central_domain' => 'example.test',
            'tenancy.central_fallback_tenant' => null,
        ]);
        $this->seed(PlansSeeder::class);

        $this->owner = User::factory()->create();
        $this->tenant = Tenant::factory()->forOwner($this->owner)->create();
        $this->tenant->users()->attach($this->owner->id, ['role' => Tenant::ROLE_OWNER, 'joined_at' => now()]);
    }

    public function test_owner_sees_the_gateway_form_on_the_billing_page(): void
    {
        $this->actingAs($this->owner)->get($this->url('/workspace/billing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspace/billing/index')
                ->where('can_manage_payments', true)
                ->where('payment_gateway', null)
                ->where('stripe_webhook.url', $this->url('/webhooks/stripe'))
            );
    }

    public function test_owner_saves_stripe_keys_and_checkout_uses_them(): void
    {
        $this->actingAs($this->owner)->put($this->url('/workspace/billing/gateway'), [
            'environment' => 'sandbox',
            'publishable_key' => 'pk_test_owner',
            'secret_key' => 'sk_test_owner',
            'webhook_secret' => 'whsec_owner',
            'is_active' => true,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $gateway = PaymentGateway::query()->forTenant($this->tenant)->where('provider', 'stripe')->sole();
        $this->assertTrue($gateway->is_active);
        $this->assertSame('whsec_owner', $gateway->webhook_secret);
        // Encrypted at rest.
        $this->assertStringNotContainsString('sk_test_owner', (string) DB::table('payment_gateways')->value('credentials'));

        app(TenantContext::class)->set($this->tenant);
        $this->assertSame(
            ['publishable_key' => 'pk_test_owner', 'secret_key' => 'sk_test_owner'],
            app(StripeCredentials::class)->keys(),
        );
    }

    public function test_blank_secrets_keep_the_saved_ones(): void
    {
        $this->save(['publishable_key' => 'pk_test_a', 'secret_key' => 'sk_test_a', 'webhook_secret' => 'whsec_a']);

        $this->save(['environment' => 'sandbox', 'publishable_key' => 'pk_test_b', 'secret_key' => '', 'webhook_secret' => '', 'is_active' => false]);

        $gateway = PaymentGateway::query()->forTenant($this->tenant)->sole();
        $this->assertSame('pk_test_b', $gateway->credentials['publishable_key']);
        $this->assertSame('sk_test_a', $gateway->credentials['secret_key']);
        $this->assertSame('whsec_a', $gateway->webhook_secret);
        $this->assertFalse($gateway->is_active);
    }

    public function test_page_never_sends_secret_values(): void
    {
        $this->save(['publishable_key' => 'pk_test_a', 'secret_key' => 'sk_test_hidden', 'webhook_secret' => 'whsec_hidden']);

        $response = $this->actingAs($this->owner)->get($this->url('/workspace/billing'))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->where('payment_gateway.publishable_key', 'pk_test_a')
            ->where('payment_gateway.has_secret_key', true)
            ->where('payment_gateway.has_webhook_secret', true)
        );
        $this->assertStringNotContainsString('sk_test_hidden', $response->getContent());
        $this->assertStringNotContainsString('whsec_hidden', $response->getContent());
    }

    public function test_first_save_requires_both_keys_in_the_right_format(): void
    {
        $this->actingAs($this->owner)->put($this->url('/workspace/billing/gateway'), [
            'environment' => 'sandbox',
            'publishable_key' => 'sk_test_wrong_field',
            'secret_key' => '',
            'webhook_secret' => 'not-a-secret',
        ])->assertSessionHasErrors(['publishable_key', 'secret_key', 'webhook_secret']);

        $this->assertSame(0, PaymentGateway::query()->forTenant($this->tenant)->count());
    }

    public function test_members_cannot_see_or_change_the_gateway(): void
    {
        $member = User::factory()->create();
        $this->tenant->users()->attach($member->id, ['role' => Tenant::ROLE_MEMBER, 'joined_at' => now()]);

        $this->actingAs($member)->get($this->url('/workspace/billing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_manage_payments', false)
                ->where('payment_gateway', null)
                ->where('stripe_webhook', null)
            );

        $this->actingAs($member)->put($this->url('/workspace/billing/gateway'), [
            'environment' => 'sandbox',
            'publishable_key' => 'pk_test_x',
            'secret_key' => 'sk_test_x',
        ])->assertForbidden();

        $this->actingAs($member)->post($this->url('/workspace/billing/gateway/test'))->assertForbidden();
    }

    public function test_workspace_admin_can_manage_the_gateway(): void
    {
        $admin = User::factory()->create();
        $this->tenant->users()->attach($admin->id, ['role' => Tenant::ROLE_ADMIN, 'joined_at' => now()]);

        $this->actingAs($admin)->put($this->url('/workspace/billing/gateway'), [
            'environment' => 'sandbox',
            'publishable_key' => 'pk_test_admin',
            'secret_key' => 'sk_test_admin',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, PaymentGateway::query()->forTenant($this->tenant)->count());
    }

    public function test_test_connection_checks_the_saved_keys_with_stripe(): void
    {
        $this->app->instance(StripeGateway::class, new class extends StripeGateway
        {
            public function verifySecretKey(string $secret): void {}
        });

        $this->actingAs($this->owner)->post($this->url('/workspace/billing/gateway/test'))
            ->assertSessionHas('error', 'Save your Stripe keys first.');

        $this->save(['publishable_key' => 'pk_test_a', 'secret_key' => 'sk_test_a']);

        $this->actingAs($this->owner)->post($this->url('/workspace/billing/gateway/test'))
            ->assertSessionHas('success', 'Connected to Stripe — the keys work.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(array $data): void
    {
        $this->actingAs($this->owner)
            ->put($this->url('/workspace/billing/gateway'), [
                'environment' => 'sandbox',
                'is_active' => true,
                ...$data,
            ])
            ->assertSessionHasNoErrors();
    }

    private function url(string $path): string
    {
        return "http://{$this->tenant->slug}.example.test{$path}";
    }
}
