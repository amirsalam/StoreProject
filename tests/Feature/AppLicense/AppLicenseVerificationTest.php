<?php

namespace Tests\Feature\AppLicense;

use App\Domain\AppLicense\AppLicenseEvaluation;
use App\Domain\AppLicense\AppLicenseEvaluator;
use App\Domain\AppLicense\AppLicenseStateRepository;
use App\Domain\AppLicense\AppLicenseTokenVerifier;
use App\Domain\AppLicense\InvalidLicenseToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLicenseVerificationTest extends TestCase
{
    use MintsLicenseTokens, RefreshDatabase;

    private const ISSUER = 'https://license.test';

    private const DOMAIN = 'shop.example.com';

    private const INSTALLATION = '00000000-0000-4000-8000-000000000001';

    private const ACTIVATION = '01k0000000000000000000000b';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://www.shop.example.com', 'app_license.server_url' => self::ISSUER]);
        $this->trustTestSigningKey(self::ISSUER);
    }

    private function verify(string $token): mixed
    {
        return app(AppLicenseTokenVerifier::class)->verify($token, self::DOMAIN, self::INSTALLATION, self::ACTIVATION);
    }

    private function assertRejected(string $token, string $reason, string $case = ''): void
    {
        try {
            $this->verify($token);
            $this->fail("Token accepted: {$case}");
        } catch (InvalidLicenseToken $e) {
            $this->assertSame($reason, $e->reason, $case);
        }
    }

    /** Store an activation whose identity matches the default minted claims. */
    private function storeToken(string $token): void
    {
        $repo = app(AppLicenseStateRepository::class);
        $repo->identity();
        $repo->update(['installation_id' => self::INSTALLATION, 'activation_id' => self::ACTIVATION, 'bound_domain' => self::DOMAIN, 'token' => $token]);
    }

    private function evaluate(): AppLicenseEvaluation
    {
        $evaluator = app(AppLicenseEvaluator::class);
        $evaluator->forget();

        return $evaluator->evaluate();
    }

    // --- the verifier ---------------------------------------------------------------------------

    public function test_a_genuine_token_for_this_installation_is_accepted()
    {
        $token = $this->verify($this->mintToken());

        $this->assertSame('test-key', $token->keyId);
        $this->assertSame(self::ACTIVATION, $token->activationId);
        $this->assertSame(self::DOMAIN, $token->domain);
        $this->assertSame('active', $token->licenseStatus);
        $this->assertSame('production', $token->environment);
    }

    public function test_tampered_and_forged_tokens_are_rejected()
    {
        $genuine = $this->mintToken();
        [$h, $p, $s] = explode('.', $genuine);
        $forgedPayload = self::b64url(json_encode(['dom' => 'attacker.com'] + json_decode(base64_decode(strtr($p, '-_', '+/')), true)));
        $flipped = base64_decode(strtr($s, '-_', '+/'));
        $flipped[0] = chr(ord($flipped[0]) ^ 1);
        $stranger = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());

        $cases = [
            'payload edited' => ["{$h}.{$forgedPayload}.{$s}", 'bad_signature'],
            'signature bit flipped' => ["{$h}.{$p}.".self::b64url($flipped), 'bad_signature'],
            'signed by a stranger' => [$this->mintToken(secret: $stranger), 'bad_signature'],
            'signature missing' => ["{$h}.{$p}.", 'bad_signature'],
            'alg none' => [$this->mintToken(header: ['alg' => 'none']), 'unsupported_header'],
            'alg HS256' => [$this->mintToken(header: ['alg' => 'HS256']), 'unsupported_header'],
            'wrong typ' => [$this->mintToken(header: ['typ' => 'JWT']), 'unsupported_header'],
            'unknown kid' => [$this->mintToken(header: ['kid' => 'nope']), 'untrusted_key'],
            'truncated' => ["{$h}.{$p}", 'malformed'],
            'extra segment' => ["{$genuine}.AAAA", 'malformed'],
            'padded base64' => ["{$h}=.{$p}.{$s}", 'malformed'],
            'garbage' => ['not.a.token', 'malformed'],
            'empty' => ['', 'malformed'],
        ];

        foreach ($cases as $case => [$token, $reason]) {
            $this->assertRejected($token, $reason, $case);
        }
    }

    public function test_a_token_for_another_installation_or_server_is_rejected()
    {
        $cases = [
            'other domain' => [['dom' => 'other.com'], 'wrong_domain'],
            'subdomain' => [['dom' => 'eu.shop.example.com'], 'wrong_domain'],
            'other installation' => [['iid' => '00000000-0000-4000-8000-000000000002'], 'wrong_installation'],
            'other activation' => [['act' => '01k0000000000000000000000z'], 'wrong_activation'],
            'other product' => [['aud' => 'other-app'], 'wrong_audience'],
            'other issuer' => [['iss' => 'https://evil.test'], 'wrong_issuer'],
            'from the future' => [['nbf' => now()->addHour()->getTimestamp()], 'not_yet_valid'],
        ];

        foreach ($cases as $case => [$claims, $reason]) {
            $this->assertRejected($this->mintToken($claims), $reason, $case);
        }
    }

    public function test_issuer_comparison_ignores_a_trailing_slash_and_case()
    {
        config(['app_license.issuer' => 'HTTPS://License.test/']);

        $this->assertSame(self::ACTIVATION, $this->verify($this->mintToken(['iss' => 'https://license.test']))->activationId);
    }

    public function test_a_small_clock_difference_is_tolerated()
    {
        $this->assertSame(self::ACTIVATION, $this->verify($this->mintToken(['nbf' => now()->addMinutes(4)->getTimestamp()]))->activationId);
    }

    public function test_malformed_claims_are_rejected()
    {
        $cases = [
            'missing sub' => [['sub' => ''], 'bad_claims'],
            'exp as string' => [['exp' => (string) now()->getTimestamp()], 'bad_claims'],
            'lic_exp as string' => [['lic_exp' => 'never'], 'bad_claims'],
            'unknown env' => [['env' => 'staging'], 'bad_claims'],
            'grace before exp' => [['grace_exp' => now()->getTimestamp()], 'bad_claims'],
            'future version' => [['ver' => 2], 'unsupported_version'],
        ];

        foreach ($cases as $case => [$claims, $reason]) {
            $this->assertRejected($this->mintToken($claims), $reason, $case);
        }
    }

    public function test_the_development_key_is_not_trusted_in_production()
    {
        // The shipped dev key may only be trusted in local/testing.
        $this->app['env'] = 'production';
        config(['app_license.public_keys' => []]);

        $this->assertRejected($this->mintToken(header: ['kid' => 'dev-2026-10']), 'untrusted_key');
    }

    // --- the evaluator ------------------------------------------------------------------------------

    public function test_no_activation_means_not_activated()
    {
        $this->assertSame(AppLicenseEvaluation::NOT_ACTIVATED, $this->evaluate()->state);
    }

    public function test_a_valid_current_token_means_licensed()
    {
        $this->storeToken($this->mintToken());

        $evaluation = $this->evaluate();
        $this->assertSame(AppLicenseEvaluation::LICENSED, $evaluation->state);
        $this->assertTrue($evaluation->allowsUse());
    }

    public function test_past_the_refresh_deadline_the_grace_period_applies_then_ends()
    {
        $this->storeToken($this->mintToken());

        $this->travel(73)->hours();
        $grace = $this->evaluate();
        $this->assertSame(AppLicenseEvaluation::GRACE, $grace->state);
        $this->assertTrue($grace->allowsUse());
        $this->assertNotNull($grace->graceEndsAt);

        $this->travel(14)->days();
        $over = $this->evaluate();
        $this->assertSame(AppLicenseEvaluation::UNLICENSED, $over->state);
        $this->assertSame('grace_expired', $over->reason);
        $this->assertFalse($over->allowsUse());
    }

    public function test_config_can_shorten_but_never_extend_the_grace_period()
    {
        $this->storeToken($this->mintToken());

        config(['app_license.max_grace_days' => 2]);
        $this->travel(72 + 3 * 24)->hours();
        $this->assertSame('grace_expired', $this->evaluate()->reason);

        config(['app_license.max_grace_days' => 365]);
        $this->assertSame(AppLicenseEvaluation::GRACE, $this->evaluate()->state, 'Within the signed grace period.');
        $this->travel(15)->days();
        $this->assertSame('grace_expired', $this->evaluate()->reason, 'A long config value must not extend the signed grace.');
    }

    public function test_a_non_active_license_status_means_unlicensed()
    {
        foreach (['suspended', 'revoked', 'expired'] as $status) {
            $this->storeToken($this->mintToken(['sts' => $status]));

            $evaluation = $this->evaluate();
            $this->assertSame(AppLicenseEvaluation::UNLICENSED, $evaluation->state, $status);
            $this->assertSame("license_{$status}", $evaluation->reason);
        }
    }

    public function test_a_license_past_its_own_expiry_is_unlicensed()
    {
        $this->storeToken($this->mintToken(['lic_exp' => now()->subMinute()->getTimestamp()]));

        $this->assertSame('license_expired', $this->evaluate()->reason);
    }

    public function test_a_forged_or_foreign_stored_token_means_unlicensed()
    {
        $this->storeToken($this->mintToken(secret: sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())));
        $this->assertSame('token_bad_signature', $this->evaluate()->reason);

        $this->storeToken($this->mintToken(['dom' => 'other.com']));
        $this->assertSame('token_wrong_domain', $this->evaluate()->reason);
    }

    public function test_changing_app_url_invalidates_the_stored_token()
    {
        $this->storeToken($this->mintToken());
        config(['app.url' => 'https://copied-site.com']);

        $evaluation = $this->evaluate();
        $this->assertSame(AppLicenseEvaluation::UNLICENSED, $evaluation->state);
        $this->assertSame('token_wrong_domain', $evaluation->reason);
    }

    public function test_the_evaluation_is_memoized_within_a_request()
    {
        $this->storeToken($this->mintToken());
        $evaluator = app(AppLicenseEvaluator::class);

        $first = $evaluator->evaluate();
        app(AppLicenseStateRepository::class)->update(['token' => null]);

        $this->assertSame($first, app(AppLicenseEvaluator::class)->evaluate(), 'Scoped: same instance and result in one request.');
        $evaluator->forget();
        $this->assertSame(AppLicenseEvaluation::NOT_ACTIVATED, $evaluator->evaluate()->state);
    }
}
