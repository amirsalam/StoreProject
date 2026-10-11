<?php

namespace Tests\Feature\AppLicense;

use App\Domain\AppLicense\AppLicenseEvaluator;
use App\Domain\AppLicense\AppLicenseStateRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppLicenseEnforcementTest extends TestCase
{
    use MintsLicenseTokens, RefreshDatabase;

    private const SERVER = 'https://license.test';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app_license.enforce' => true,
            'app_license.server_url' => self::SERVER,
            'app.url' => 'https://www.shop.example.com',
        ]);
        $this->trustTestSigningKey(self::SERVER);
    }

    /** Store a genuine token for this installation (licensed unless claims say otherwise). */
    private function license(array $claims = []): void
    {
        $repo = app(AppLicenseStateRepository::class);
        $identity = $repo->identity();
        $repo->update([
            'activation_id' => '01k0000000000000000000000b',
            'bound_domain' => 'shop.example.com',
            'token' => $this->mintToken(array_merge(['iid' => $identity->installationId], $claims)),
        ]);
        app(AppLicenseEvaluator::class)->forget();
    }

    // --- the route map (regression guard) -------------------------------------------------------------

    /** @return list<string> "METHOD uri" of every route behind app.licensed */
    private function protectedRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $r) => in_array('app.licensed', $r->gatherMiddleware(), true)
                && ! in_array('app.licensed', $r->excludedMiddleware(), true))
            ->map(fn (RoutingRoute $r) => $r->methods()[0].' '.$r->uri())
            ->sort()->values()->all();
    }

    public function test_exactly_the_intended_routes_are_protected()
    {
        $expected = collect(Route::getRoutes()->getRoutes())
            ->filter(function (RoutingRoute $r) {
                $uri = $r->uri();

                return (str_starts_with($uri, 'admin/') && ! str_starts_with($uri, 'admin/app-license'))
                    || str_starts_with($uri, 'workspace/')
                    || str_starts_with($uri, 'uploads/')
                    || (str_starts_with($uri, 'api/v1/') && ! str_starts_with($uri, 'api/v1/licenses'))
                    || in_array($r->getName(), ['checkout.show', 'checkout.store', 'checkout.paypal.pay', 'checkout.cmi.redirect'], true);
            })
            ->map(fn (RoutingRoute $r) => $r->methods()[0].' '.$r->uri())
            ->sort()->values()->all();

        $this->assertNotEmpty($expected);
        $this->assertSame($expected, $this->protectedRoutes());
    }

    public function test_critical_routes_are_never_protected()
    {
        $protected = $this->protectedRoutes();
        $mustStayOpen = [
            // activation itself
            'GET admin/app-license', 'POST admin/app-license/activate', 'POST admin/app-license/deactivate',
            // sign-in and recovery
            'GET login', 'POST login', 'POST logout', 'GET forgot-password', 'POST reset-password',
            'GET two-factor-challenge', 'GET settings/password', 'PUT settings/password', 'GET settings/two-factor',
            // finishing payments that already started
            'POST webhooks/stripe', 'POST payments/cmi/callback',
            'GET checkout/{order}/cmi/ok', 'GET checkout/{order}/cmi/fail',
            'GET checkout/{order}/paypal/return', 'GET checkout/{order}/paypal/cancel', 'GET checkout/{order}/confirmation',
            // what buyers already paid for
            'GET purchases', 'GET purchases/downloads/{download}', 'GET invoices/{invoice}',
            // storefront, health, product-license API
            'GET /', 'GET products', 'GET cart', 'GET up',
            'POST api/v1/licenses/activate', 'POST api/v1/licenses/deactivate', 'GET api/v1/licenses/validate',
        ];

        $all = collect(Route::getRoutes()->getRoutes())->map(fn (RoutingRoute $r) => $r->methods()[0].' '.$r->uri())->all();
        foreach ($mustStayOpen as $route) {
            $this->assertContains($route, $all, "Route [{$route}] no longer exists — update this list.");
            $this->assertNotContains($route, $protected, "Route [{$route}] must never require the installation license.");
        }
    }

    // --- behaviour while unlicensed ------------------------------------------------------------------------

    public function test_an_unlicensed_admin_is_sent_to_the_activation_page()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/products')
            ->assertRedirect('/admin/app-license')
            ->assertSessionHas('error');

        $this->actingAs($admin)->get('/admin/app-license')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/app-license/edit')
            ->where('license.state', 'not_activated'));
    }

    public function test_activation_stays_possible_while_unlicensed()
    {
        Http::fake([self::SERVER.'/*' => Http::response(['error' => ['code' => 'invalid_license', 'message' => 'x']], 404)]);

        $this->actingAs(User::factory()->admin()->create())->from('/admin/app-license')
            ->post('/admin/app-license/activate', ['license_key' => 'SP-AAAAA-BBBBB-CCCCC-DDDDD-EEEEE-FFFFF'])
            ->assertRedirect('/admin/app-license')
            ->assertSessionHasErrors('license_key');

        Http::assertSentCount(1);
    }

    public function test_non_admins_still_get_403_on_admin_pages_not_the_license_page()
    {
        $this->actingAs(User::factory()->create())->get('/admin/products')->assertForbidden();
    }

    public function test_other_users_get_a_503_unavailable_page_on_licensed_areas()
    {
        $user = User::factory()->create();

        foreach (['/checkout', '/workspace/projects'] as $url) {
            $this->actingAs($user)->get($url)
                ->assertStatus(503)
                ->assertHeader('Retry-After', '3600')
                ->assertInertia(fn (Assert $page) => $page->component('app-license-unavailable'));
        }
    }

    public function test_storefront_and_buyers_purchases_keep_working()
    {
        $this->get('/')->assertOk();
        $this->get('/products')->assertOk();
        $this->actingAs(User::factory()->create())->get('/purchases')->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_the_business_api_answers_403_json_but_the_product_license_api_does_not()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/user')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'app_license_required');

        $productApi = $this->postJson('/api/v1/licenses/activate', []);
        $this->assertNotSame('app_license_required', $productApi->json('error.code'));
        $this->assertNotSame(403, $productApi->status());
    }

    public function test_payment_webhooks_answer_the_same_whether_licensed_or_not()
    {
        // The controller has its own answers (e.g. 503 "webhook secret not
        // configured" in tests); the license gate must not change any of them.
        foreach (['/webhooks/stripe', '/payments/cmi/callback'] as $url) {
            config(['app_license.enforce' => true]);
            $enforced = $this->post($url, []);
            config(['app_license.enforce' => false]);
            $open = $this->post($url, []);

            $this->assertSame($open->status(), $enforced->status(), $url);
            $this->assertSame($open->getContent(), $enforced->getContent(), $url);
        }
    }

    // --- licensed / grace / revoked ---------------------------------------------------------------------------

    public function test_a_licensed_installation_works_normally()
    {
        $this->license();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/products')->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('app_license', null));
        $this->withToken($admin->createToken('t')->plainTextToken)->getJson('/api/v1/user')->assertOk();
    }

    public function test_the_grace_period_keeps_working_and_warns_admins_only()
    {
        $this->license();
        $this->travel(73)->hours();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/products')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('app_license.state', 'grace')
            ->where('app_license.grace_ends_at', fn ($value) => is_string($value)));

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('app_license', null));
    }

    public function test_a_revoked_or_expired_grace_license_is_enforced()
    {
        $this->license(['sts' => 'revoked']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/products')->assertRedirect('/admin/app-license');

        $this->license();
        $this->travel(72 + 15 * 24)->hours();
        $this->actingAs($admin)->get('/admin/products')->assertRedirect('/admin/app-license');
    }

    public function test_a_copied_installation_on_another_domain_is_not_licensed()
    {
        $this->license();
        config(['app.url' => 'https://copied-shop.com']);
        app(AppLicenseEvaluator::class)->forget();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/products')->assertRedirect('/admin/app-license');
    }

    // --- the enforcement switch --------------------------------------------------------------------------------

    public function test_enforcement_can_be_turned_off_only_in_local_and_testing()
    {
        config(['app_license.enforce' => false]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/products')->assertOk();

        $this->app['env'] = 'production';
        $this->actingAs($admin)->get('/admin/products')->assertRedirect('/admin/app-license');
    }
}
