<?php

namespace Tests\Feature\AppLicense;

use App\Domain\AppLicense\AppLicenseEvaluation;
use App\Domain\AppLicense\AppLicenseEvaluator;
use App\Domain\AppLicense\AppLicenseStateRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLicenseSecurityTest extends TestCase
{
    use MintsLicenseTokens, RefreshDatabase;

    private const ISSUER = 'https://license.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://www.shop.example.com', 'app_license.server_url' => self::ISSUER, 'app_license.issuer' => self::ISSUER]);
    }

    private function keyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [sodium_crypto_sign_secretkey($pair), base64_encode(sodium_crypto_sign_publickey($pair))];
    }

    private function storeTokenSignedBy(string $kid, string $secret): void
    {
        $repo = app(AppLicenseStateRepository::class);
        $identity = $repo->identity();
        $repo->update([
            'activation_id' => '01k0000000000000000000000b',
            'bound_domain' => 'shop.example.com',
            'token' => $this->mintToken(['iid' => $identity->installationId], ['kid' => $kid], $secret),
        ]);
    }

    private function evaluate(): AppLicenseEvaluation
    {
        $evaluator = app(AppLicenseEvaluator::class);
        $evaluator->forget();

        return $evaluator->evaluate();
    }

    public function test_a_submitted_license_key_is_never_flashed_into_the_session()
    {
        $tooLong = 'SP-'.str_repeat('A', 80);

        $this->actingAs(User::factory()->admin()->create())->from('/admin/app-license')
            ->post('/admin/app-license/activate', ['license_key' => $tooLong])
            ->assertSessionHasErrors('license_key');

        $this->assertNull(session()->getOldInput('license_key'));
        $this->assertStringNotContainsString($tooLong, json_encode(session()->all()));
    }

    public function test_signing_key_rotation_keeps_installations_licensed()
    {
        [$oldSecret, $oldPublic] = $this->keyPair();
        [$newSecret, $newPublic] = $this->keyPair();

        // Release N: only the old key is trusted and signing.
        config(['app_license.public_keys' => ['2026-10' => $oldPublic]]);
        $this->storeTokenSignedBy('2026-10', $oldSecret);
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->evaluate()->state);

        // Release N+1 ships the NEW public key before the server switches.
        config(['app_license.public_keys' => ['2026-10' => $oldPublic, '2027-01' => $newPublic]]);
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->evaluate()->state, 'Old tokens stay valid during rotation.');
        $this->storeTokenSignedBy('2027-01', $newSecret);
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->evaluate()->state, 'New tokens are accepted.');

        // Release N+2 drops the old key: old tokens stop working, new ones keep going.
        config(['app_license.public_keys' => ['2027-01' => $newPublic]]);
        $this->assertSame(AppLicenseEvaluation::LICENSED, $this->evaluate()->state);
        $this->storeTokenSignedBy('2026-10', $oldSecret);
        $this->assertSame('token_untrusted_key', $this->evaluate()->reason);
    }

    public function test_a_token_signed_by_a_key_from_another_kid_is_rejected()
    {
        [, $trustedPublic] = $this->keyPair();
        [$attackerSecret] = $this->keyPair();
        config(['app_license.public_keys' => ['2026-10' => $trustedPublic]]);

        // Right kid, wrong key: the kid only SELECTS a trusted key.
        $this->storeTokenSignedBy('2026-10', $attackerSecret);
        $this->assertSame('token_bad_signature', $this->evaluate()->reason);
    }

    public function test_storeproject_ships_public_keys_only()
    {
        $configured = (array) require config_path('app_license.php');
        $keys = array_merge($configured['public_keys'], $configured['development_public_keys']);

        $this->assertNotEmpty($keys);
        foreach ($keys as $kid => $encoded) {
            $this->assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen((string) base64_decode($encoded, true)), "Key [{$kid}] is not a 32-byte public key — never paste a private signing key here.");
        }
    }
}
