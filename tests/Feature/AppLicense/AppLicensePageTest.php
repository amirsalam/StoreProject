<?php

namespace Tests\Feature\AppLicense;

use App\Domain\AppLicense\AppLicenseStateRepository;
use App\Models\AppLicenseState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppLicensePageTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER = 'https://license.test';

    private const KEY = 'SP-7K2M9-QX4TB-0RZ8H-N3WJ6-CVF5P-DYS1A';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app_license.server_url' => self::SERVER, 'app.url' => 'https://www.shop.example.com']);
        $this->admin = User::factory()->admin()->create();
    }

    private function activated(array $overrides = []): array
    {
        return array_merge([
            'token' => 'aGVhZGVy.cGF5bG9hZA.c2ln',
            'token_expires_at' => '2026-10-14T12:00:00+00:00',
            'activation' => ['id' => '01k0000000000000000000000b', 'domain' => 'shop.example.com', 'environment' => 'production', 'status' => 'active'],
            'license' => ['id' => '01k0000000000000000000000a', 'status' => 'active', 'expires_at' => null],
        ], $overrides);
    }

    private function fakeServerError(string $code, int $status): void
    {
        Http::fake([self::SERVER.'/*' => Http::response(['error' => ['code' => $code, 'message' => 'x']], $status)]);
    }

    private function seedActivation(string $domain = 'shop.example.com'): AppLicenseState
    {
        app(AppLicenseStateRepository::class)->identity();

        return app(AppLicenseStateRepository::class)->update([
            'activation_id' => '01k0000000000000000000000b',
            'bound_domain' => $domain,
            'environment' => 'production',
            'license_status' => 'active',
        ]);
    }

    // --- access ------------------------------------------------------------------------------

    public function test_the_page_is_admin_only()
    {
        $this->get('/admin/app-license')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/app-license')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/admin/app-license/activate', ['license_key' => self::KEY])->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/admin/app-license/deactivate')->assertForbidden();
    }

    public function test_an_unactivated_installation_shows_its_domain_and_the_form()
    {
        $this->actingAs($this->admin)->get('/admin/app-license')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/app-license/edit')
            ->where('license.activated', false)
            ->where('license.current_domain', 'shop.example.com')
            ->where('license.server_configured', true)
            ->where('license.domain_changed', false));
    }

    // --- activation ------------------------------------------------------------------------------

    public function test_activating_stores_the_activation()
    {
        Http::fake([self::SERVER.'/api/v1/licenses/activate' => Http::response($this->activated(), 201)]);

        $this->actingAs($this->admin)->from('/admin/app-license')
            ->post('/admin/app-license/activate', ['license_key' => self::KEY])
            ->assertRedirect('/admin/app-license')
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $state = AppLicenseState::sole();
        $this->assertSame('01k0000000000000000000000b', $state->activation_id);
        $this->assertSame('shop.example.com', $state->bound_domain);
        $this->assertSame('production', $state->environment);
        $this->assertSame('active', $state->license_status);
        $this->assertNotNull($state->last_success_at);
        $this->assertNull($state->last_error_code);

        // The central APP_URL is sent, never the request host.
        Http::assertSent(fn (Request $r) => $r['url'] === 'https://www.shop.example.com' && $r['installation_id'] === $state->installation_id);

        $this->actingAs($this->admin)->get('/admin/app-license')->assertInertia(fn (Assert $page) => $page
            ->where('license.activated', true)
            ->where('license.bound_domain', 'shop.example.com'));
    }

    public function test_the_license_key_is_never_stored()
    {
        Http::fake([self::SERVER.'/*' => Http::response($this->activated(), 201)]);

        $this->actingAs($this->admin)->post('/admin/app-license/activate', ['license_key' => self::KEY]);

        $this->assertStringNotContainsString(self::KEY, json_encode(DB::table('app_license_state')->get()));
        $this->assertStringNotContainsString(self::KEY, json_encode(session()->all()));
    }

    public function test_a_refused_key_shows_a_clear_error_and_activates_nothing()
    {
        $this->fakeServerError('invalid_license', 404);

        $this->actingAs($this->admin)->from('/admin/app-license')
            ->post('/admin/app-license/activate', ['license_key' => self::KEY])
            ->assertSessionHasErrors(['license_key' => 'This license key is not valid for StoreProject. Check it and try again.']);

        $this->assertNull(AppLicenseState::sole()->activation_id);
        $this->assertSame('invalid_license', AppLicenseState::sole()->last_error_code);
    }

    public function test_each_failure_has_an_explanation()
    {
        $cases = [
            ['domain_limit_reached', 409, 'already active on another domain'],
            ['license_revoked', 403, 'revoked'],
            ['rate_limited', 429, 'Too many attempts'],
            ['server_error', 500, 'could not be reached'],
        ];

        foreach ($cases as [$code, $status, $expected]) {
            Http::swap(new Factory(app('events')));
            $this->fakeServerError($code, $status);

            $response = $this->actingAs($this->admin)->from('/admin/app-license')->post('/admin/app-license/activate', ['license_key' => self::KEY]);

            $this->assertStringContainsString($expected, session('errors')->first('license_key'), $code);
            $response->assertRedirect('/admin/app-license');
        }
    }

    public function test_an_unreachable_server_is_reported_without_activating()
    {
        Http::fake(fn () => Http::failedConnection('down'));
        config(['app_license.http.retry_delay_ms' => 1]);

        $this->actingAs($this->admin)->from('/admin/app-license')
            ->post('/admin/app-license/activate', ['license_key' => self::KEY])
            ->assertSessionHasErrors(['license_key' => 'The license server could not be reached. Please try again in a few minutes.']);

        $this->assertSame('network_error', AppLicenseState::sole()->last_error_code);
    }

    public function test_an_unconfigured_server_is_reported_without_any_request()
    {
        config(['app_license.server_url' => null]);
        Http::fake();

        $this->actingAs($this->admin)->get('/admin/app-license')->assertInertia(fn (Assert $page) => $page->where('license.server_configured', false));
        $this->actingAs($this->admin)->post('/admin/app-license/activate', ['license_key' => self::KEY])
            ->assertSessionHasErrors(['license_key' => 'The license server address is not configured (APP_LICENSE_SERVER_URL).']);

        Http::assertNothingSent();
    }

    public function test_the_key_is_required()
    {
        Http::fake();

        $this->actingAs($this->admin)->post('/admin/app-license/activate', ['license_key' => ''])->assertSessionHasErrors('license_key');

        Http::assertNothingSent();
    }

    public function test_an_activated_installation_cannot_be_activated_twice()
    {
        $this->seedActivation();
        Http::fake();

        $this->actingAs($this->admin)->post('/admin/app-license/activate', ['license_key' => self::KEY])
            ->assertSessionHasErrors(['license_key' => 'This installation is already activated.']);

        Http::assertNothingSent();
    }

    // --- moved installation ---------------------------------------------------------------------------

    public function test_a_changed_app_url_is_flagged_and_reactivates_with_a_new_identity()
    {
        $old = $this->seedActivation('old-shop.com');
        $oldInstallation = $old->installation_id;

        $this->actingAs($this->admin)->get('/admin/app-license')->assertInertia(fn (Assert $page) => $page
            ->where('license.domain_changed', true)
            ->where('license.bound_domain', 'old-shop.com')
            ->where('license.current_domain', 'shop.example.com'));

        Http::fake([self::SERVER.'/*' => Http::response($this->activated(), 201)]);
        $this->actingAs($this->admin)->post('/admin/app-license/activate', ['license_key' => self::KEY])->assertSessionHasNoErrors();

        $state = AppLicenseState::sole();
        $this->assertNotSame($oldInstallation, $state->installation_id, 'A moved installation must not reuse the old identity.');
        $this->assertSame('shop.example.com', $state->bound_domain);
    }

    // --- deactivation -------------------------------------------------------------------------------------

    public function test_deactivating_releases_the_activation()
    {
        $this->seedActivation();
        Http::fake([self::SERVER.'/api/v1/licenses/deactivate' => Http::response(['activation' => ['id' => 'x', 'status' => 'deactivated'], 'changed' => true])]);

        $this->actingAs($this->admin)->from('/admin/app-license')->post('/admin/app-license/deactivate')
            ->assertRedirect('/admin/app-license')
            ->assertSessionHas('success');

        $state = AppLicenseState::sole();
        $this->assertNull($state->activation_id);
        $this->assertNull($state->bound_domain);
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Signature') && $r->header('X-Activation-Id')[0] === '01k0000000000000000000000b');
    }

    public function test_a_failed_deactivation_keeps_the_activation_so_it_can_be_retried()
    {
        $this->seedActivation();
        $this->fakeServerError('server_error', 503);

        $this->actingAs($this->admin)->post('/admin/app-license/deactivate')->assertSessionHas('error');

        $this->assertSame('01k0000000000000000000000b', AppLicenseState::sole()->activation_id);
    }

    public function test_deactivating_an_activation_the_server_already_forgot_clears_it_locally()
    {
        $this->seedActivation();
        $this->fakeServerError('activation_not_found', 404);

        $this->actingAs($this->admin)->post('/admin/app-license/deactivate')->assertSessionHas('success');

        $this->assertNull(AppLicenseState::sole()->activation_id);
    }

    // --- separation from product licensing --------------------------------------------------------------------

    public function test_product_licensing_admin_page_is_unaffected()
    {
        $this->actingAs($this->admin)->get('/admin/licensing')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/licensing/edit'));
    }
}
