<?php

namespace App\Domain\AppLicense;

use Carbon\CarbonImmutable;

/**
 * Claims of a token whose signature, issuer, audience and binding to this
 * installation were checked by AppLicenseTokenVerifier. Time-based
 * validity (exp / grace) is judged separately by AppLicenseEvaluator.
 */
final class VerifiedLicenseToken
{
    public function __construct(
        public readonly string $keyId,
        public readonly string $licenseId,
        public readonly string $activationId,
        public readonly string $installationId,
        public readonly string $domain,
        public readonly string $environment,
        public readonly string $licenseStatus,
        public readonly ?CarbonImmutable $licenseExpiresAt,
        public readonly CarbonImmutable $issuedAt,
        public readonly CarbonImmutable $expiresAt,
        public readonly CarbonImmutable $graceExpiresAt,
    ) {}
}
