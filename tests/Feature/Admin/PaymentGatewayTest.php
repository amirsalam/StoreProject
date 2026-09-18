<?php

namespace Tests\Feature\Admin;

use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin payment-gateways management — CRUD, RBAC, credential encryption,
 * secret redaction, default handling, toggling, and audit.
 */
class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/payment-gateways')
            ->assertForbidden();
    }

    public function test_admin_can_list_gateways(): void
    {
        PaymentGateway::factory()->create(['display_name' => 'Stripe']);

        $this->actingAs($this->admin())
            ->get('/admin/payment-gateways')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/payment-gateways/index')
                ->has('gateways.data', 1)
            );
    }

    public function test_admin_can_create_a_gateway_and_credentials_are_encrypted_at_rest(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/payment-gateways', [
            'provider' => 'stripe',
            'name' => 'Stripe',
            'display_name' => 'Stripe Checkout',
            'environment' => 'sandbox',
            'credentials' => [
                'publishable_key' => 'pk_test_visible',
                'secret_key' => 'sk_test_TOPSECRET',
            ],
            'webhook_secret' => 'whsec_TOPSECRET',
            'fee_fixed' => '0.30',
            'fee_percent' => '2.9',
        ])->assertRedirect(route('admin.payment-gateways.index'));

        $gateway = PaymentGateway::query()->firstOrFail();
        $this->assertSame('Stripe Checkout', $gateway->display_name);
        $this->assertSame('sk_test_TOPSECRET', $gateway->credentials['secret_key']);

        // Raw DB value must NOT contain the plaintext secret (encrypted at rest).
        $rawCreds = DB::table('payment_gateways')->where('id', $gateway->id)->value('credentials');
        $rawWebhook = DB::table('payment_gateways')->where('id', $gateway->id)->value('webhook_secret');
        $this->assertStringNotContainsString('sk_test_TOPSECRET', (string) $rawCreds);
        $this->assertStringNotContainsString('whsec_TOPSECRET', (string) $rawWebhook);

        // Audited.
        $this->assertDatabaseHas('activity_logs', ['event' => 'payment_gateway.created']);
    }

    public function test_secret_values_are_never_sent_to_the_frontend(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'credentials' => ['publishable_key' => 'pk_x', 'secret_key' => 'sk_test_TOPSECRET'],
            'webhook_secret' => 'whsec_TOPSECRET',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/payment-gateways')->assertDontSee('sk_test_TOPSECRET');
        $this->actingAs($admin)
            ->get(route('admin.payment-gateways.edit', $gateway))
            ->assertOk()
            ->assertDontSee('sk_test_TOPSECRET')
            ->assertDontSee('whsec_TOPSECRET');
    }

    public function test_update_preserves_secret_when_field_left_blank(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'credentials' => ['publishable_key' => 'pk_old', 'secret_key' => 'sk_KEEP_ME'],
        ]);

        $this->actingAs($this->admin())->put(route('admin.payment-gateways.update', $gateway), [
            'provider' => 'stripe',
            'name' => 'Stripe',
            'display_name' => 'Renamed',
            'environment' => 'production',
            'credentials' => ['publishable_key' => 'pk_new', 'secret_key' => ''], // blank secret
            'fee_fixed' => '0.30',
            'fee_percent' => '2.9',
        ])->assertRedirect(route('admin.payment-gateways.index'));

        $gateway->refresh();
        $this->assertSame('Renamed', $gateway->display_name);
        $this->assertSame('production', $gateway->environment);
        $this->assertSame('pk_new', $gateway->credentials['publishable_key']);
        $this->assertSame('sk_KEEP_ME', $gateway->credentials['secret_key']); // preserved
    }

    public function test_setting_default_unsets_other_defaults(): void
    {
        $a = PaymentGateway::factory()->default()->create(['provider' => 'stripe']);
        $b = PaymentGateway::factory()->create(['provider' => 'paypal', 'is_default' => false]);

        $this->actingAs($this->admin())
            ->post(route('admin.payment-gateways.default', $b))
            ->assertRedirect();

        $this->assertFalse($a->refresh()->is_default);
        $this->assertTrue($b->refresh()->is_default);
        $this->assertDatabaseHas('activity_logs', ['event' => 'payment_gateway.default_set']);
    }

    public function test_toggle_flips_active_state(): void
    {
        $gateway = PaymentGateway::factory()->inactive()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.payment-gateways.toggle', $gateway))
            ->assertRedirect();

        $this->assertTrue($gateway->refresh()->is_active);
    }

    public function test_admin_can_delete_a_gateway(): void
    {
        $gateway = PaymentGateway::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.payment-gateways.destroy', $gateway))
            ->assertRedirect(route('admin.payment-gateways.index'));

        $this->assertDatabaseMissing('payment_gateways', ['id' => $gateway->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'payment_gateway.deleted']);
    }

    public function test_test_connection_flags_missing_credentials(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'credentials' => ['publishable_key' => 'pk_only'], // secret_key (required) missing
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.payment-gateways.test', $gateway))
            ->assertSessionHas('error');

        $this->assertNull($gateway->refresh()->last_connection_at);
    }

    public function test_test_connection_succeeds_with_complete_credentials(): void
    {
        $gateway = PaymentGateway::factory()->create([
            'credentials' => ['publishable_key' => 'pk_x', 'secret_key' => 'sk_x'],
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.payment-gateways.test', $gateway))
            ->assertSessionHas('success');

        $this->assertNotNull($gateway->refresh()->last_connection_at);
    }

    public function test_validation_rejects_unknown_provider(): void
    {
        $this->actingAs($this->admin())->post('/admin/payment-gateways', [
            'provider' => 'not_a_real_provider',
            'name' => 'X',
            'display_name' => 'X',
            'environment' => 'sandbox',
            'fee_fixed' => '0',
            'fee_percent' => '0',
        ])->assertSessionHasErrors('provider');
    }
}
