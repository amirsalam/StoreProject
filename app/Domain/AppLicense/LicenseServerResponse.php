<?php

namespace App\Domain\AppLicense;

use Carbon\CarbonImmutable;

/**
 * A successful activate / verify answer. The token is UNVERIFIED here:
 * AppLicenseTokenVerifier (Step 16) must check it before it is trusted.
 */
final class LicenseServerResponse
{
    public function __construct(
        public readonly string $token,
        public readonly CarbonImmutable $tokenExpiresAt,
        public readonly string $licenseStatus,
        public readonly ?string $activationId = null,
        public readonly ?string $domain = null,
        public readonly ?string $environment = null,
    ) {}
}
