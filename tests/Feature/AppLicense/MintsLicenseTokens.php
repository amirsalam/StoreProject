<?php

namespace Tests\Feature\AppLicense;

/**
 * Signs license tokens exactly like IndependentLicenseServer does, with a
 * per-test key registered as trusted (kid "test-key").
 */
trait MintsLicenseTokens
{
    protected string $signingSecret;

    protected function trustTestSigningKey(string $issuer): void
    {
        $pair = sodium_crypto_sign_keypair();
        $this->signingSecret = sodium_crypto_sign_secretkey($pair);

        config([
            'app_license.public_keys' => ['test-key' => base64_encode(sodium_crypto_sign_publickey($pair))],
            'app_license.issuer' => $issuer,
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims  overrides
     * @param  array<string, mixed>  $header  overrides
     */
    protected function mintToken(array $claims = [], array $header = [], ?string $secret = null): string
    {
        $now = now()->getTimestamp();

        $payload = array_merge([
            'iss' => config('app_license.issuer'),
            'aud' => 'storeproject',
            'sub' => '01k0000000000000000000000a',
            'act' => '01k0000000000000000000000b',
            'iid' => '00000000-0000-4000-8000-000000000001',
            'dom' => 'shop.example.com',
            'env' => 'production',
            'sts' => 'active',
            'lic_exp' => null,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 72 * 3600,
            'grace_exp' => $now + 14 * 86400,
            'jti' => bin2hex(random_bytes(16)),
            'ver' => 1,
        ], $claims);

        $head = array_merge(['alg' => 'EdDSA', 'typ' => 'SPLIC', 'kid' => 'test-key'], $header);
        $input = self::b64url(json_encode($head, JSON_UNESCAPED_SLASHES)).'.'.self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $input.'.'.self::b64url(sodium_crypto_sign_detached($input, $secret ?? $this->signingSecret));
    }

    protected static function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
