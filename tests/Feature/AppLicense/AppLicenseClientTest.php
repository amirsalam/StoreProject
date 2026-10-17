<?php

namespace Tests\Feature\AppLicense;

use App\Domain\AppLicense\AppLicenseClient;
use App\Domain\AppLicense\AppLicenseStateRepository;
use App\Domain\AppLicense\InstallationIdentity;
use App\Domain\AppLicense\LicenseServerException;
use App\Domain\AppLicense\PublicKeyring;
use App\Models\AppLicenseState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AppLicenseClientTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER = 'https://license.test';

    private const TOKEN = 'aGVhZGVy.cGF5bG9hZA.c2ln';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app_license.server_url' => self::SERVER]);
    }

    private function client(): AppLicenseClient
    {
        return app(AppLicenseClient::class);
    }

    private function tokenBody(array $extra = []): array
    {
        return array_merge([
            'token' => self::TOKEN,
            'token_expires_at' => '2026-10-14T12:00:00+00:00',
            'license' => ['id' => '01k0000000000000000000000a', 'status' => 'active', 'expires_at' => null],
        ], $extra);
    }

    /**
     * Http::fake() APPENDS stubs (the first match keeps winning), so a loop
     * needs a fresh factory per case to really change the response.
     */
    private function fakeOnly(mixed $response): void
    {
        Http::swap(new HttpFactory(app('events')));
        Http::fake([self::SERVER.'/*' => $response]);
    }

    private function error(string $code, int $status): array
    {
        return ['error' => ['code' => $code, 'message' => "Server says {$code}"]];
    }

    /** Re-derive the server's signed string and check the signature, like the server does. */
    private function assertSignedBy(Request $request, InstallationIdentity $identity, string $path, string $activationId): void
    {
        $this->assertSame($activationId, $request->header('X-Activation-Id')[0]);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{16,64}$/', $request->header('X-Nonce')[0]);
        $this->assertEqualsWithDelta(now()->getTimestamp(), (int) $request->header('X-Timestamp')[0], 2);

        $canonical = implode("\n", ['POST', $path, $request->header('X-Timestamp')[0], $request->header('X-Nonce')[0], hash('sha256', $request->body())]);
        $signature = base64_decode(strtr($request->header('X-Signature')[0], '-_', '+/'));

        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $request->header('X-Signature')[0], 'Signature must be unpadded base64url.');
        $this->assertTrue(sodium_crypto_sign_verify_detached($signature, $canonical, base64_decode($identity->publicKey)));
    }

    // --- identity & state storage ------------------------------------------------------------

    public function test_the_identity_is_created_once_and_reused()
    {
        $repo = app(AppLicenseStateRepository::class);

        $first = $repo->identity();
        $again = $repo->identity();

        $this->assertSame($first->installationId, $again->installationId);
        $this->assertSame($first->publicKey, $again->publicKey);
        $this->assertSame(1, AppLicenseState::count());
        $this->assertTrue(sodium_crypto_sign_verify_detached($again->sign('m'), 'm', base64_decode($first->publicKey)));
    }

    public function test_the_secret_key_is_encrypted_at_rest_and_never_serialized()
    {
        $identity = app(AppLicenseStateRepository::class)->identity();
        $raw = DB::table('app_license_state')->value('secret_key');
        $state = app(AppLicenseStateRepository::class)->current();

        $this->assertStringNotContainsString($identity->exportSecretKey(), $raw);
        $this->assertSame($identity->exportSecretKey(), $state->secret_key);
        $this->assertArrayNotHasKey('secret_key', $state->toArray());
        $this->assertStringNotContainsString($identity->exportSecretKey(), print_r($identity, true));
    }

    public function test_a_reset_creates_a_fresh_identity_and_forgets_the_activation()
    {
        $repo = app(AppLicenseStateRepository::class);
        $old = $repo->identity();
        $repo->update(['activation_id' => '01k0000000000000000000000a', 'bound_domain' => 'example.com']);

        $new = $repo->resetIdentity();

        $this->assertNotSame($old->installationId, $new->installationId);
        $this->assertNotSame($old->publicKey, $new->publicKey);
        $this->assertNull($repo->current()->activation_id);
        $this->assertSame(1, AppLicenseState::count());
    }

    public function test_a_changed_app_key_is_reported_clearly()
    {
        app(AppLicenseStateRepository::class)->identity();

        // Simulate APP_KEY rotation: the encrypted cast now uses another key.
        Model::encryptUsing(new Encrypter(random_bytes(32), 'AES-256-CBC'));

        try {
            app(AppLicenseStateRepository::class)->identity();
            $this->fail('An undecryptable key must not pass silently.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('APP_KEY', $e->getMessage());
        } finally {
            Model::encryptUsing(null);
        }
    }

    public function test_the_state_table_is_not_tenant_scoped()
    {
        $this->assertFalse(Schema::hasColumn('app_license_state', 'tenant_id'));
    }

    // --- activate --------------------------------------------------------------------------------

    public function test_activate_sends_the_key_url_and_public_key_and_parses_the_answer()
    {
        Http::fake([self::SERVER.'/api/v1/licenses/activate' => Http::response($this->tokenBody([
            'activation' => ['id' => '01k0000000000000000000000b', 'domain' => 'example.com', 'environment' => 'production', 'status' => 'active'],
        ]), 201)]);
        $identity = InstallationIdentity::generate();

        $result = $this->client()->activate('SP-AAAAA-BBBBB-CCCCC-DDDDD-EEEEE-FFFFF', 'https://www.example.com', $identity);

        $this->assertSame('01k0000000000000000000000b', $result->activationId);
        $this->assertSame('example.com', $result->domain);
        $this->assertSame('production', $result->environment);
        $this->assertSame(self::TOKEN, $result->token);
        $this->assertSame('active', $result->licenseStatus);
        $this->assertSame('2026-10-14 12:00:00', $result->tokenExpiresAt->utc()->toDateTimeString());

        Http::assertSent(fn (Request $r) => $r->url() === self::SERVER.'/api/v1/licenses/activate'
            && $r['license_key'] === 'SP-AAAAA-BBBBB-CCCCC-DDDDD-EEEEE-FFFFF'
            && $r['product'] === 'storeproject'
            && $r['url'] === 'https://www.example.com'
            && $r['installation_id'] === $identity->installationId
            && $r['installation_public_key'] === $identity->publicKey
            && $r['app_version'] === config('app_license.app_version')
            && ! $r->hasHeader('X-Signature')
            && ! str_contains($r->body(), $identity->exportSecretKey()));
    }

    // --- signed endpoints ----------------------------------------------------------------------------

    public function test_verify_is_signed_exactly_as_the_server_expects()
    {
        Http::fake([self::SERVER.'/*' => Http::response($this->tokenBody())]);
        $identity = InstallationIdentity::generate();

        $result = $this->client()->verify('01k0000000000000000000000b', 'https://example.com', $identity);

        $this->assertSame(self::TOKEN, $result->token);
        Http::assertSent(function (Request $r) use ($identity) {
            $this->assertSame(self::SERVER.'/api/v1/licenses/verify', $r->url());
            $this->assertSame($identity->installationId, $r['installation_id']);
            $this->assertSame('https://example.com', $r['url']);
            $this->assertSignedBy($r, $identity, '/api/v1/licenses/verify', '01k0000000000000000000000b');

            return true;
        });
    }

    public function test_a_server_under_a_sub_path_still_gets_the_api_path_signed()
    {
        config(['app_license.server_url' => 'https://example.org/licensing/']);
        Http::fake(['example.org/*' => Http::response($this->tokenBody())]);
        $identity = InstallationIdentity::generate();

        $this->client()->verify('01k0000000000000000000000b', 'https://example.com', $identity);

        Http::assertSent(function (Request $r) use ($identity) {
            $this->assertSame('https://example.org/licensing/api/v1/licenses/verify', $r->url());
            $this->assertSignedBy($r, $identity, '/api/v1/licenses/verify', '01k0000000000000000000000b');

            return true;
        });
    }

    public function test_deactivate_is_signed_and_reports_whether_anything_changed()
    {
        Http::fakeSequence(self::SERVER.'/*')
            ->push(['activation' => ['id' => 'x', 'status' => 'deactivated'], 'changed' => true])
            ->push(['activation' => ['id' => 'x', 'status' => 'deactivated'], 'changed' => false]);
        $identity = InstallationIdentity::generate();

        $this->assertTrue($this->client()->deactivate('01k0000000000000000000000b', $identity));
        $this->assertFalse($this->client()->deactivate('01k0000000000000000000000b', $identity));

        Http::assertSent(function (Request $r) use ($identity) {
            $this->assertSignedBy($r, $identity, '/api/v1/licenses/deactivate', '01k0000000000000000000000b');

            return true;
        });
    }

    public function test_every_request_gets_a_fresh_nonce()
    {
        Http::fake([self::SERVER.'/*' => Http::response($this->tokenBody())]);
        $identity = InstallationIdentity::generate();

        $this->client()->verify('01k0000000000000000000000b', 'https://example.com', $identity);
        $this->client()->verify('01k0000000000000000000000b', 'https://example.com', $identity);

        $nonces = Http::recorded()->map(fn ($pair) => $pair[0]->header('X-Nonce')[0])->unique();
        $this->assertCount(2, $nonces);
    }

    // --- error classification -------------------------------------------------------------------------

    public function test_server_refusals_are_definitive_and_transient_trouble_is_not()
    {
        $cases = [
            ['license_revoked', 403, true],
            ['license_suspended', 403, true],
            ['license_expired', 403, true],
            ['activation_not_found', 404, true],
            ['installation_mismatch', 403, true],
            ['invalid_license', 404, true],
            ['domain_limit_reached', 409, true],
            ['rate_limited', 429, false],
            ['request_expired', 401, false],
            ['signature_invalid', 401, false],
            ['replay_detected', 401, false],
            ['invalid_request', 422, false],
        ];

        foreach ($cases as [$code, $status, $definitive]) {
            $this->fakeOnly(Http::response($this->error($code, $status), $status));

            try {
                $this->client()->verify('01k0000000000000000000000b', 'https://example.com', InstallationIdentity::generate());
                $this->fail("No exception for {$code}");
            } catch (LicenseServerException $e) {
                $this->assertSame($code, $e->errorCode);
                $this->assertSame($status, $e->httpStatus);
                $this->assertSame($definitive, $e->isDefinitive(), $code);
            }
        }
    }

    public function test_garbled_or_foreign_responses_are_never_definitive()
    {
        $cases = [
            '500 html' => [Http::response('<html>Oops</html>', 500), 'server_error'],
            '502 json without code' => [Http::response(['message' => 'bad gateway'], 502), 'server_error'],
            '200 not json' => [Http::response('hello', 200), 'invalid_response'],
            '200 without token' => [Http::response(['license' => ['status' => 'active']], 200), 'invalid_response'],
            '200 malformed token' => [Http::response($this->tokenBody(['token' => 'not-a-token']), 200), 'invalid_response'],
            '200 bad expiry' => [Http::response($this->tokenBody(['token_expires_at' => 'soon']), 200), 'invalid_response'],
            '403 captive portal html' => [Http::response('<html>Login to wifi</html>', 403), 'invalid_response'],
            '400 weird code' => [Http::response(['error' => ['code' => 'DROP TABLE;']], 400), 'invalid_response'],
        ];

        foreach ($cases as $case => [$response, $expected]) {
            $this->fakeOnly($response);

            try {
                $this->client()->verify('01k0000000000000000000000b', 'https://example.com', InstallationIdentity::generate());
                $this->fail("No exception for {$case}");
            } catch (LicenseServerException $e) {
                $this->assertSame($expected, $e->errorCode, $case);
                $this->assertFalse($e->isDefinitive(), $case);
            }
        }
    }

    public function test_connection_failures_are_retried_then_reported_as_network_errors()
    {
        config(['app_license.http.retry_delay_ms' => 1]);
        $attempts = 0;
        // Http::failedConnection() rejects like a real dropped connection.
        // (Throwing from the fake closure instead crashes PHP under Xdebug
        // 3.3.2 once another test has run in the same process.)
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::failedConnection('Connection refused');
        });

        try {
            $this->client()->verify('01k0000000000000000000000b', 'https://example.com', InstallationIdentity::generate());
            $this->fail('No exception');
        } catch (LicenseServerException $e) {
            $this->assertSame('network_error', $e->errorCode);
            $this->assertFalse($e->isDefinitive());
        }

        $this->assertSame(2, $attempts, 'One retry after a connection failure.');
    }

    public function test_an_answer_is_never_retried()
    {
        Http::fake([self::SERVER.'/*' => Http::response($this->error('license_revoked', 403), 403)]);

        try {
            $this->client()->verify('01k0000000000000000000000b', 'https://example.com', InstallationIdentity::generate());
        } catch (LicenseServerException) {
        }

        Http::assertSentCount(1);
    }

    public function test_redirects_are_not_followed()
    {
        Http::fake([
            self::SERVER.'/*' => Http::response('', 302, ['Location' => 'http://evil.test/steal']),
            'evil.test/*' => Http::response($this->tokenBody()),
        ]);

        try {
            $this->client()->verify('01k0000000000000000000000b', 'https://example.com', InstallationIdentity::generate());
            $this->fail('A redirect must not succeed.');
        } catch (LicenseServerException $e) {
            $this->assertSame('invalid_response', $e->errorCode);
        }

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.test'));
    }

    // --- configuration ------------------------------------------------------------------------------------

    public function test_a_missing_or_invalid_server_url_is_reported_without_any_request()
    {
        Http::fake();

        foreach ([null, '', 'not a url', 'ftp://license.test'] as $url) {
            config(['app_license.server_url' => $url]);

            try {
                $this->client()->verify('01k0000000000000000000000b', 'https://example.com', InstallationIdentity::generate());
                $this->fail('No exception for '.var_export($url, true));
            } catch (LicenseServerException $e) {
                $this->assertSame('not_configured', $e->errorCode);
                $this->assertFalse($e->isDefinitive());
            }
        }

        Http::assertNothingSent();
    }

    public function test_plain_http_is_refused_outside_local_and_testing()
    {
        Http::fake([
            '*' => Http::response($this->tokenBody()),
        ]);
        config(['app_license.server_url' => 'http://license.test']);

        $this->client()->verify('01k0000000000000000000000b', 'https://example.com', InstallationIdentity::generate());

        $this->app['env'] = 'production';
        $this->expectExceptionObject(LicenseServerException::notConfigured('APP_LICENSE_SERVER_URL must use https.'));
        $this->client()->verify('01k0000000000000000000000b', 'https://example.com', InstallationIdentity::generate());
    }

    public function test_development_public_keys_are_trusted_only_in_local_and_testing()
    {
        config(['app_license.public_keys' => ['prod-1' => base64_encode(random_bytes(32)), 'broken' => 'not-base64!', 'short' => base64_encode('x')]]);

        $this->assertEqualsCanonicalizing(['prod-1', 'dev-2026-10'], array_keys(PublicKeyring::trusted()));

        $this->app['env'] = 'production';
        $this->assertSame(['prod-1'], array_keys(PublicKeyring::trusted()));
    }
}
