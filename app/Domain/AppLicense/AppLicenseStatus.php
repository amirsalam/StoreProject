<?php

namespace App\Domain\AppLicense;

use App\Models\AppLicenseState;

/**
 * Read-only snapshot for the Installation license page. It reports what is
 * stored and what the server last said; deciding whether the installation
 * is currently licensed (token signature, expiry, grace) comes later.
 */
final class AppLicenseStatus
{
    public function __construct(
        public readonly bool $activated,
        public readonly ?string $activationId,
        public readonly ?string $boundDomain,
        public readonly ?string $currentDomain,
        public readonly ?string $environment,
        public readonly ?string $licenseStatus,
        public readonly ?string $tokenExpiresAt,
        public readonly ?string $lastSuccessAt,
        public readonly ?string $lastCheckedAt,
        public readonly ?string $lastErrorCode,
        public readonly bool $serverConfigured,
    ) {}

    public static function from(?AppLicenseState $state, ?string $currentDomain): self
    {
        return new self(
            activated: (bool) $state?->activation_id,
            activationId: $state?->activation_id,
            boundDomain: $state?->bound_domain,
            currentDomain: $currentDomain,
            environment: $state?->environment,
            licenseStatus: $state?->license_status,
            tokenExpiresAt: $state?->token_expires_at?->toIso8601String(),
            lastSuccessAt: $state?->last_success_at?->toIso8601String(),
            lastCheckedAt: $state?->last_checked_at?->toIso8601String(),
            lastErrorCode: $state?->last_error_code,
            serverConfigured: filled(config('app_license.server_url')),
        );
    }

    /** A different APP_URL than the one the activation was made for. */
    public function domainChanged(): bool
    {
        return $this->activated && $this->boundDomain !== null && $this->boundDomain !== $this->currentDomain;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'activated' => $this->activated,
            'activation_id' => $this->activationId,
            'bound_domain' => $this->boundDomain,
            'current_domain' => $this->currentDomain,
            'domain_changed' => $this->domainChanged(),
            'environment' => $this->environment,
            'license_status' => $this->licenseStatus,
            'token_expires_at' => $this->tokenExpiresAt,
            'last_success_at' => $this->lastSuccessAt,
            'last_checked_at' => $this->lastCheckedAt,
            'last_error_code' => $this->lastErrorCode,
            'server_configured' => $this->serverConfigured,
        ];
    }
}
