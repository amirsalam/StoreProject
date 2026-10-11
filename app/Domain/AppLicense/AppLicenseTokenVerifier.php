<?php

namespace App\Domain\AppLicense;

use Carbon\CarbonImmutable;
use JsonException;

/**
 * Verifies a license token signed by IndependentLicenseServer:
 *
 *   base64url(header).base64url(payload).base64url(Ed25519 signature)
 *   header  { alg: "EdDSA", typ: "SPLIC", kid }
 *   payload { iss, aud, sub, act, iid, dom, env, sts, lic_exp,
 *             iat, nbf, exp, grace_exp, jti, ver: 1 }
 *
 * Checks, in order: structure → header → trusted key → signature →
 * claim types → issuer / audience → binding to THIS installation
 * (domain, installation id, activation id) → not-before.
 *
 * A valid token proves the license was valid WHEN SIGNED. Whether it is
 * still current (exp, grace, status) is AppLicenseEvaluator's decision.
 */
class AppLicenseTokenVerifier
{
    /**
     * @throws InvalidLicenseToken
     */
    public function verify(string $token, string $expectedDomain, string $expectedInstallationId, string $expectedActivationId): VerifiedLicenseToken
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidLicenseToken('malformed');
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($encodedHeader);
        if (($header['alg'] ?? null) !== 'EdDSA' || ($header['typ'] ?? null) !== 'SPLIC' || ! is_string($header['kid'] ?? null)) {
            throw new InvalidLicenseToken('unsupported_header');
        }

        $publicKey = PublicKeyring::trusted()[$header['kid']] ?? null;
        if ($publicKey === null) {
            throw new InvalidLicenseToken('untrusted_key');
        }

        $signature = self::base64UrlDecode($encodedSignature);
        if ($signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $encodedHeader.'.'.$encodedPayload, $publicKey)) {
            throw new InvalidLicenseToken('bad_signature');
        }

        $claims = $this->decodeJson($encodedPayload);
        $this->assertShape($claims);

        if (! hash_equals(self::normalizeUrl((string) config('app_license.issuer')), self::normalizeUrl($claims['iss']))) {
            throw new InvalidLicenseToken('wrong_issuer');
        }
        if ($claims['aud'] !== (string) config('app_license.product')) {
            throw new InvalidLicenseToken('wrong_audience');
        }
        if ($claims['dom'] !== $expectedDomain) {
            throw new InvalidLicenseToken('wrong_domain');
        }
        if (! hash_equals(strtolower($expectedInstallationId), strtolower($claims['iid']))) {
            throw new InvalidLicenseToken('wrong_installation');
        }
        if (! hash_equals($expectedActivationId, $claims['act'])) {
            throw new InvalidLicenseToken('wrong_activation');
        }

        $skew = (int) config('app_license.clock_skew_seconds', 300);
        if ($claims['nbf'] > now()->getTimestamp() + $skew) {
            throw new InvalidLicenseToken('not_yet_valid');
        }

        return new VerifiedLicenseToken(
            keyId: $header['kid'],
            licenseId: $claims['sub'],
            activationId: $claims['act'],
            installationId: $claims['iid'],
            domain: $claims['dom'],
            environment: $claims['env'],
            licenseStatus: $claims['sts'],
            licenseExpiresAt: $claims['lic_exp'] === null ? null : CarbonImmutable::createFromTimestamp($claims['lic_exp']),
            issuedAt: CarbonImmutable::createFromTimestamp($claims['iat']),
            expiresAt: CarbonImmutable::createFromTimestamp($claims['exp']),
            graceExpiresAt: CarbonImmutable::createFromTimestamp($claims['grace_exp']),
        );
    }

    /** @param array<string, mixed> $c */
    private function assertShape(array $c): void
    {
        $strings = ['iss', 'aud', 'sub', 'act', 'iid', 'dom', 'env', 'sts', 'jti'];
        $ints = ['iat', 'nbf', 'exp', 'grace_exp'];

        foreach ($strings as $key) {
            if (! is_string($c[$key] ?? null) || $c[$key] === '') {
                throw new InvalidLicenseToken('bad_claims');
            }
        }
        foreach ($ints as $key) {
            if (! is_int($c[$key] ?? null)) {
                throw new InvalidLicenseToken('bad_claims');
            }
        }
        if (! array_key_exists('lic_exp', $c) || ! ($c['lic_exp'] === null || is_int($c['lic_exp']))) {
            throw new InvalidLicenseToken('bad_claims');
        }
        if (($c['ver'] ?? null) !== 1) {
            throw new InvalidLicenseToken('unsupported_version');
        }
        if (! in_array($c['env'], ['production', 'development'], true) || $c['exp'] < $c['iat'] || $c['grace_exp'] < $c['exp']) {
            throw new InvalidLicenseToken('bad_claims');
        }
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $encoded): array
    {
        $raw = self::base64UrlDecode($encoded);

        try {
            $data = $raw === null ? null : json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidLicenseToken('malformed');
        }

        return $data;
    }

    /** Strict, canonical base64url (no padding, no stray bits). */
    private static function base64UrlDecode(string $text): ?string
    {
        if ($text === '' || preg_match('/^[A-Za-z0-9_-]+$/', $text) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($text, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        return rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=') === $text ? $decoded : null;
    }

    private static function normalizeUrl(string $url): string
    {
        return strtolower(rtrim(trim($url), '/'));
    }
}
