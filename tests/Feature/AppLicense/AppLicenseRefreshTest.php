<?php

namespace Tests\Feature\AppLicense;

use App\Domain\AppLicense\AppLicenseEvaluation;
use App\Domain\AppLicense\AppLicenseEvaluator;
use App\Domain\AppLicense\AppLicenseManager;
use App\Domain\AppLicense\AppLicenseStateRepository;
use App\Models\AppLicenseState;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppLicenseRefreshTest extends TestCase
{
    use MintsLicenseTokens, RefreshDatabase;

    private const SERVER = 'https://license.test';

    private const ACTIVATION = '01k0000000000000000000000b';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app_license.enforce' => true,
            'app_license.server_url' => self::SERVER,
            'app_license.http.retry_delay_ms' => 1,
            'app.url' => 'https://www.shop.example.com',
        ]);
        $this->trustTestSigningKey(self::SERVER);
    }

    /** An activated installation with a fresh genuine token. */
    private function activated(): string
    {
        $repo = app(AppLicenseStateRepository::class);
        $identity = $repo->identity();
        $repo->update([
            'activation_id' => self::ACTIVATION,
            'bound_domain' => 'shop.example.com',
            'token' => $this->mintToken(['iid' => $identity->installationId]),
            'token_expires_at' => now()->addHours(72),
            'last_checked_at' => now(),
            'last_success_at' => now(),
        ]);

        return $identity->installationId;
    }

    private function serverAnswers(mixed $response): void
    {
        Http::swap(new HttpFactory(app('events')));
        Http::fake([self::SERVER.'/*' => $response]);
    }

    /** The real server's verify answer: a token signed for the asking installation. */
    private function serverConfirms(array $claims = []): void
    {
        $this->serverAnswers(fn (Request $r) => Http::response([
            'token' => $this->mintToken(array_merge(['iid' => $r['installation_id'], 'act' => self::ACTIVATION], $claims)),
            'token_expires_at' => now()->addHours(72)->toIso8601String(),
            'license' => ['id' => 'x', 'status' => $claims['sts'] ?? 'active', 'expires_at' => null],
        ]));
    }

    private function serverRefuses(string $code, int $status): void
    {
        $this->serverAnswers(Http::response(['error' => ['code' => $code, 'message' => 'x']], $status));
    }

    private function state(): AppLicenseEvaluation
    {
        $evaluator = app(AppLicenseEvaluator::class);
        $evaluator->forget();

        return $evaluator->evaluate();
    }

    private function manager(): AppLicenseManager
    {
        return app(AppLicenseManager::class);
    }

    // --- outcomes -----------------------------------------------------------------------------------

    public function test_a_successful_check_in_stores_a_fresh_verified_token()
    {
        $this->activated();
        $before = AppLicenseState::sole()->token;
        $this->travel(21)->hours();
        $this->serverConfirms();

        $this->assertSame('refreshed', $this->manager()->refresh());

        $state = AppLicenseState::sole();
        $this->assertNotSame($before, $state->token);
        $this->assertNull($state->last_error_code);
        $this->assertTrue($state->last_success_at->isToday() || $state->last_success_at->greaterThan(now()->subMinute()));
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->state()->state);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/licenses/verify') && $r->hasHeader('X-Signature'));
    }

    public function test_an_outage_keeps_the_installation_running_through_grace_then_recovers()
    {
        $this->activated();

        // Server down for days: the current token carries on, then grace…
        $this->serverAnswers(fn () => Http::failedConnection('down'));
        $this->travel(21)->hours();
        $this->assertSame('failed', $this->manager()->refresh());
        $this->assertSame('network_error', AppLicenseState::sole()->last_error_code);
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->state()->state, 'Outages never lock out early.');

        $this->travel(52)->hours(); // past exp (72 h)
        $this->assertSame('failed', $this->manager()->refresh());
        $this->assertSame(AppLicenseEvaluation::GRACE, $this->state()->state);

        // …and once the server is back, the next check-in restores it.
        $this->serverConfirms();
        $this->assertSame('refreshed', $this->manager()->refresh());
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->state()->state);
        $this->assertNull(AppLicenseState::sole()->last_error_code);
    }

    public function test_after_the_grace_period_the_installation_is_unlicensed_until_the_server_answers()
    {
        $this->activated();
        $this->travel(72 + 15 * 24)->hours();
        $this->assertSame('grace_expired', $this->state()->reason);

        $this->serverConfirms();
        $this->manager()->refresh();
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->state()->state, 'Recovery as soon as the server answers.');
    }

    public function test_temporary_errors_are_never_a_verdict()
    {
        foreach ([['server_error', 503], ['rate_limited', 429], ['request_expired', 401], ['signature_invalid', 401]] as [$code, $status]) {
            $this->activated();
            $this->serverRefuses($code, $status);

            $this->assertSame('failed', $this->manager()->refresh(), $code);
            $this->assertSame(AppLicenseEvaluation::LICENSED, $this->state()->state, $code);
        }
    }

    public function test_an_unverifiable_answer_keeps_the_current_token()
    {
        $this->activated();
        $token = AppLicenseState::sole()->token;
        $this->serverConfirms(['dom' => 'elsewhere.com']);

        $this->assertSame('failed', $this->manager()->refresh());
        $this->assertSame($token, AppLicenseState::sole()->token);
        $this->assertSame('token_invalid', AppLicenseState::sole()->last_error_code);
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->state()->state);
    }

    public function test_a_revoked_or_suspended_license_takes_effect_immediately_and_reinstatement_recovers()
    {
        foreach ([['license_revoked', 403], ['license_suspended', 403], ['license_expired', 403]] as [$code, $status]) {
            $this->activated();
            $this->serverRefuses($code, $status);

            $this->assertSame('refused', $this->manager()->refresh(), $code);
            $evaluation = $this->state();
            $this->assertSame(AppLicenseEvaluation::UNLICENSED, $evaluation->state, "{$code}: even though the old token is still within exp");
            $this->assertSame($code, $evaluation->reason);
            $this->assertSame(self::ACTIVATION, AppLicenseState::sole()->activation_id, 'Activation kept for a reinstatement.');
        }

        // Reinstated on the server: the next check-in brings it back.
        $this->travel(2)->hours();
        $this->serverConfirms();
        $this->assertSame('refreshed', $this->manager()->refresh());
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->state()->state);
    }

    public function test_a_released_or_replaced_activation_must_be_activated_again()
    {
        foreach (['activation_not_found' => 404, 'installation_mismatch' => 403] as $code => $status) {
            $this->activated();
            $this->serverRefuses($code, $status);

            $this->assertSame('released', $this->manager()->refresh(), $code);
            $state = AppLicenseState::sole();
            $this->assertNull($state->activation_id);
            $this->assertNull($state->token);
            $this->assertSame($code, $state->last_error_code);
            $this->assertSame(AppLicenseEvaluation::NOT_ACTIVATED, $this->state()->state);
        }
    }

    public function test_nothing_is_sent_when_not_activated_or_when_app_url_moved()
    {
        Http::fake();
        $this->assertSame('not_activated', $this->manager()->refresh());

        $this->activated();
        config(['app.url' => 'https://copied-shop.com']);
        $this->assertSame('domain_changed', $this->manager()->refresh());

        Http::assertNothingSent();
    }

    // --- when check-ins are due ---------------------------------------------------------------------------

    public function test_check_ins_are_about_daily_with_hourly_retries_after_failure()
    {
        $this->activated();
        $this->serverAnswers(fn () => Http::failedConnection('down'));

        $this->assertFalse($this->manager()->isRefreshDue(), 'Just checked.');
        $this->travel(19)->hours();
        $this->assertFalse($this->manager()->isRefreshDue(), 'Last success < 20 h ago.');
        $this->travel(2)->hours();
        $this->assertTrue($this->manager()->isRefreshDue());

        $this->manager()->refreshIfDue(); // fails
        $this->assertFalse($this->manager()->isRefreshDue(), 'At most one attempt per hour.');
        $this->travel(61)->minutes();
        $this->assertTrue($this->manager()->isRefreshDue(), 'Retry an hour later.');
    }

    public function test_refresh_if_due_does_nothing_when_not_due()
    {
        $this->activated();
        Http::fake();

        $this->assertNull($this->manager()->refreshIfDue());
        Http::assertNothingSent();
    }

    public function test_a_concurrent_check_in_is_skipped_while_one_holds_the_lock()
    {
        $this->activated();
        $this->travel(21)->hours();
        Http::fake();
        $lock = cache()->lock('app-license:refresh', 120);
        $lock->get();

        $this->assertNull($this->manager()->refreshIfDue());
        Http::assertNothingSent();

        $lock->release();
    }

    // --- triggers --------------------------------------------------------------------------------------------

    public function test_a_licensed_area_request_runs_a_due_check_in_after_the_response()
    {
        $this->activated();
        $this->travel(21)->hours();
        $this->serverConfirms();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/products')->assertOk();

        Http::assertSentCount(1);
        $this->assertTrue(AppLicenseState::sole()->last_success_at->greaterThan(now()->subMinute()));
    }

    public function test_the_check_in_command_and_its_schedule()
    {
        $this->activated();
        $this->travel(21)->hours();
        $this->serverConfirms();

        $this->artisan('app-license:verify --if-due')->expectsOutputToContain('License confirmed')->assertSuccessful();
        $this->artisan('app-license:verify --if-due')->expectsOutputToContain('No check-in due')->assertSuccessful();

        $this->serverRefuses('license_revoked', 403);
        $this->artisan('app-license:verify')->expectsOutputToContain('refused this license')->assertFailed();

        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'app-license:verify'));
        $this->assertNotNull($event, 'app-license:verify is not scheduled.');
        $this->assertStringContainsString('--if-due', $event->command);
        $this->assertMatchesRegularExpression('/^\d{1,2} \* \* \* \*$/', $event->expression, 'Hourly at an install-specific minute.');
    }

    public function test_the_status_and_deactivate_commands()
    {
        $this->activated();

        $this->artisan('app-license:status')->expectsOutputToContain('licensed')->expectsOutputToContain('shop.example.com')->assertSuccessful();

        $this->serverAnswers(Http::response(['activation' => ['id' => 'x', 'status' => 'deactivated'], 'changed' => true]));
        $this->artisan('app-license:deactivate')
            ->expectsConfirmation('Deactivate this installation? Licensed areas stop working until it is activated again.', 'no')
            ->assertFailed();
        $this->assertNotNull(AppLicenseState::sole()->activation_id);

        $this->artisan('app-license:deactivate --force')->expectsOutputToContain('Deactivated')->assertSuccessful();
        $this->assertNull(AppLicenseState::sole()->activation_id);
    }

    public function test_check_now_works_from_the_page_even_while_unlicensed()
    {
        $this->activated();
        $this->serverRefuses('license_suspended', 403);
        $this->manager()->refresh();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/products')->assertRedirect('/admin/app-license');

        $this->serverConfirms();
        $this->actingAs($admin)->from('/admin/app-license')->post('/admin/app-license/refresh')
            ->assertRedirect('/admin/app-license')
            ->assertSessionHas('success');

        $this->actingAs($admin)->get('/admin/products')->assertOk();
    }
}
