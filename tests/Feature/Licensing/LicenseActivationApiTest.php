<?php

namespace Tests\Feature\Licensing;

use App\Events\PaymentCompleted;
use App\Models\ActivityLog;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseActivationApiTest extends TestCase
{
    use RefreshDatabase;

    private const INVALID_RESPONSE = [
        'message' => 'The license key is invalid.',
        'error' => 'invalid_license',
    ];

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin tenancy config so a local .env fallback tenant can't leak in.
        config([
            'tenancy.central_domain' => 'example.test',
            'tenancy.central_fallback_tenant' => null,
        ]);

        $this->tenant = Tenant::factory()->create();
    }

    public function test_activation_records_the_domain_and_consumes_a_slot(): void
    {
        $license = $this->license(['activation_limit' => 2]);

        $this->postJson($this->url('activate'), [
            'license_key' => $license->license_key,
            'domain' => 'shop.example.com',
        ])
            ->assertOk()
            ->assertExactJson(['data' => [
                'domain' => 'shop.example.com',
                'activated' => true,
                'status' => License::STATUS_ACTIVE,
                'product_id' => $license->product_id,
                'activation_limit' => 2,
                'activations_count' => 1,
                'activations_remaining' => 1,
                'expires_at' => null,
            ]]);

        $license->refresh();
        $this->assertSame(['shop.example.com'], $license->activated_domains);
        $this->assertSame(1, $license->activations_count);
        $this->assertTrue(ActivityLog::query()
            ->where('event', 'license.activated')
            ->where('user_id', $license->user_id)
            ->exists());
    }

    public function test_reactivating_the_same_domain_does_not_consume_another_slot(): void
    {
        $license = $this->license(['activation_limit' => 1]);

        // Same site written three ways — all normalise to one slot.
        foreach (['example.com', 'https://Example.com/wp-admin', 'EXAMPLE.COM.'] as $domain) {
            $this->postJson($this->url('activate'), [
                'license_key' => $license->license_key,
                'domain' => $domain,
            ])->assertOk()->assertJsonPath('data.activations_count', 1);
        }

        $license->refresh();
        $this->assertSame(['example.com'], $license->activated_domains);
        $this->assertSame(1, $license->activations_count);
        $this->assertSame(1, ActivityLog::query()->where('event', 'license.activated')->count());
    }

    public function test_activation_is_refused_once_the_limit_is_reached(): void
    {
        $license = $this->license([
            'activation_limit' => 2,
            'activated_domains' => ['a.example.com', 'b.example.com'],
            'activations_count' => 2,
        ]);

        $this->postJson($this->url('activate'), [
            'license_key' => $license->license_key,
            'domain' => 'c.example.com',
        ])
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'This license has reached its activation limit.',
                'error' => 'activation_limit_reached',
            ]);

        $this->assertSame(['a.example.com', 'b.example.com'], $license->refresh()->activated_domains);
    }

    public function test_an_already_activated_domain_still_succeeds_at_the_limit(): void
    {
        $license = $this->license([
            'activation_limit' => 1,
            'activated_domains' => ['example.com'],
            'activations_count' => 1,
        ]);

        $this->postJson($this->url('activate'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ])->assertOk()->assertJsonPath('data.activations_remaining', 0);
    }

    public function test_expired_licenses_cannot_be_activated_or_validated(): void
    {
        $byStatus = $this->license(['status' => License::STATUS_EXPIRED, 'expires_at' => now()->subDay()]);
        $byDate = $this->license(['expires_at' => now()->subMinute(), 'activated_domains' => ['example.com']]);

        foreach ([$byStatus, $byDate] as $license) {
            $this->postJson($this->url('activate'), [
                'license_key' => $license->license_key,
                'domain' => 'example.com',
            ])->assertForbidden()->assertJsonPath('error', 'license_expired');
        }

        $this->getJson($this->url('validate', [
            'license_key' => $byDate->license_key,
            'domain' => 'example.com',
        ]))->assertForbidden()->assertJsonPath('error', 'license_expired');

        $this->assertNull($byStatus->refresh()->activated_domains);
    }

    public function test_revoked_licenses_cannot_be_activated(): void
    {
        $license = $this->license(['status' => License::STATUS_REVOKED, 'revoked_at' => now()->subDay()]);

        $this->postJson($this->url('activate'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This license has been revoked.',
                'error' => 'license_revoked',
            ]);

        $this->assertSame(0, $license->refresh()->activations_count);
    }

    public function test_unknown_and_out_of_scope_keys_get_one_uniform_response(): void
    {
        $own = $this->license();
        $foreign = $this->license(['tenant_id' => Tenant::factory()->create()->id]);

        $attempts = [
            'unknown key' => ['license_key' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ'],
            'malformed key' => ['license_key' => 'not a key'],
            'another tenant\'s key' => ['license_key' => $foreign->license_key],
            'key for another product' => ['license_key' => $own->license_key, 'product_id' => $own->product_id + 1000],
        ];

        foreach ($attempts as $case => $payload) {
            foreach (['activate', 'deactivate'] as $action) {
                $response = $this->postJson($this->url($action), [...$payload, 'domain' => 'example.com']);
                $this->assertSame(404, $response->status(), "{$case} ({$action})");
                $this->assertSame(self::INVALID_RESPONSE, $response->json(), "{$case} ({$action})");
            }

            $this->getJson($this->url('validate', [...$payload, 'domain' => 'example.com']))
                ->assertNotFound()
                ->assertExactJson(self::INVALID_RESPONSE);
        }

        $this->assertNull($foreign->refresh()->activated_domains);
        $this->assertNull($own->refresh()->activated_domains);
    }

    public function test_keys_do_not_resolve_on_the_central_domain(): void
    {
        $license = $this->license();

        // Central host with no fallback tenant: no tenant in context.
        app(TenantContext::class)->set(null);

        $this->postJson('http://example.test/api/v1/licenses/activate', [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ])->assertNotFound()->assertExactJson(self::INVALID_RESPONSE);

        $this->assertNull($license->refresh()->activated_domains);
    }

    public function test_the_key_is_matched_case_insensitively(): void
    {
        $license = $this->license();

        $this->postJson($this->url('activate'), [
            'license_key' => strtolower($license->license_key),
            'domain' => 'example.com',
        ])->assertOk();
    }

    public function test_the_product_id_is_enforced_when_supplied(): void
    {
        $license = $this->license();

        $this->postJson($this->url('activate'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
            'product_id' => $license->product_id,
        ])->assertOk()->assertJsonPath('data.product_id', $license->product_id);
    }

    public function test_deactivation_frees_the_slot_for_another_domain(): void
    {
        $license = $this->license([
            'activation_limit' => 1,
            'activated_domains' => ['old.example.com'],
            'activations_count' => 1,
        ]);

        $this->postJson($this->url('deactivate'), [
            'license_key' => $license->license_key,
            'domain' => 'https://old.example.com/',
        ])
            ->assertOk()
            ->assertJsonPath('data.activated', false)
            ->assertJsonPath('data.activations_count', 0)
            ->assertJsonPath('data.activations_remaining', 1);

        $this->assertSame([], $license->refresh()->activated_domains);
        $this->assertSame(0, $license->activations_count);
        $this->assertTrue(ActivityLog::query()->where('event', 'license.deactivated')->exists());

        $this->postJson($this->url('activate'), [
            'license_key' => $license->license_key,
            'domain' => 'new.example.com',
        ])->assertOk();

        $this->assertSame(['new.example.com'], $license->refresh()->activated_domains);
    }

    public function test_deactivating_a_domain_without_a_slot_is_a_no_op(): void
    {
        $license = $this->license(['activated_domains' => ['keep.example.com'], 'activations_count' => 1]);

        $this->postJson($this->url('deactivate'), [
            'license_key' => $license->license_key,
            'domain' => 'other.example.com',
        ])->assertOk()->assertJsonPath('data.activations_count', 1);

        $this->assertSame(['keep.example.com'], $license->refresh()->activated_domains);
        $this->assertFalse(ActivityLog::query()->where('event', 'license.deactivated')->exists());
    }

    public function test_validate_reports_whether_the_domain_holds_a_slot(): void
    {
        $license = $this->license([
            'activated_domains' => ['example.com'],
            'activations_count' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        $this->getJson($this->url('validate', [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]))
            ->assertOk()
            ->assertJsonPath('data.activated', true)
            ->assertJsonPath('data.expires_at', $license->expires_at->toIso8601String());

        $this->getJson($this->url('validate', [
            'license_key' => $license->license_key,
            'domain' => 'elsewhere.example.com',
        ]))
            ->assertForbidden()
            ->assertJsonPath('error', 'not_activated');
    }

    public function test_a_license_fulfilled_without_tenant_context_activates_on_its_stores_host(): void
    {
        // The Stripe webhook path dispatches PaymentCompleted with no
        // tenant in context; FulfillOrder must take the tenant from the order.
        $product = Product::factory()->license()->create(['default_activation_limit' => 1]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Order::STATUS_PAID]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_type' => $product->type,
            'quantity' => 1,
            'unit_price' => $product->price,
            'total_price' => $product->price,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'status' => Payment::STATUS_SUCCEEDED,
        ]);

        app(TenantContext::class)->set(null);
        PaymentCompleted::dispatch($payment, $order);

        $license = License::query()->forTenant($this->tenant)->where('product_id', $product->id)->firstOrFail();

        $this->postJson($this->url('activate'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
            'product_id' => $product->id,
        ])->assertOk()->assertJsonPath('data.activations_remaining', 0);
    }

    public function test_invalid_input_returns_json_without_an_accept_header(): void
    {
        $this->post($this->url('activate'), ['license_key' => 'ABCD-ABCD-ABCD-ABCD', 'domain' => 'bad domain!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        $this->post($this->url('activate'), ['domain' => 'example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_key');
    }

    public function test_the_endpoints_are_rate_limited_per_ip(): void
    {
        $url = $this->url('validate', ['license_key' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ', 'domain' => 'example.com']);

        for ($i = 0; $i < 60; $i++) {
            $this->get($url)->assertNotFound();
        }

        $this->get($url)->assertStatus(429)->assertHeader('Retry-After');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function url(string $action, array $query = []): string
    {
        $url = "http://{$this->tenant->slug}.example.test/api/v1/licenses/{$action}";

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function license(array $attributes = []): License
    {
        return License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'activation_limit' => 1,
            'activations_count' => 0,
            'activated_domains' => null,
            'status' => License::STATUS_ACTIVE,
            'expires_at' => null,
            'revoked_at' => null,
            ...$attributes,
        ]);
    }
}
