<?php

namespace App\Domain\Licensing;

use App\Models\ActivityLog;
use App\Models\License;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Activation lifecycle behind the public license API
 * (/api/v1/licenses/*). The only place a license's activation slots
 * are consumed or freed.
 *
 * `activated_domains` is the source of truth; `activations_count` is
 * rewritten from it on every change so the two can't drift.
 * Activation and deactivation lock the license row, so two concurrent
 * activations can't both take the last slot.
 *
 * Tenancy: keys are looked up through the BelongsToTenant global scope,
 * so a key only resolves on its own store's host. With no tenant in
 * context the lookup is refused outright rather than relying on the
 * scope (which is bypassed in console contexts).
 */
class LicenseActivationService
{
    /** Per-IP cap for the `license-api` rate limiter (AppServiceProvider). */
    public const RATE_LIMIT_PER_MINUTE = 60;

    /**
     * Compared against when no license matched, so a miss costs the same
     * hash_equals() call as a hit.
     */
    private const MISS_SENTINEL = '0000-0000-0000-0000';

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Occupy an activation slot for $domain. Re-activating a domain that
     * already holds a slot is a no-op and does not consume another.
     */
    public function activate(string $key, string $domain, ?int $productId = null): License
    {
        return DB::transaction(function () use ($key, $domain, $productId) {
            $license = $this->find($key, $productId, lock: true);
            $this->ensureUsable($license);

            if ($license->hasActivation($domain)) {
                return $license;
            }

            if (! $license->canActivate()) {
                throw LicenseActivationException::limitReached();
            }

            $this->saveDomains($license, [...$license->activatedDomains(), $domain]);

            ActivityLog::record('license.activated', $license->user, [
                'license_id' => $license->id,
                'domain' => $domain,
            ]);

            return $license;
        });
    }

    /**
     * Free $domain's activation slot. Allowed whatever the license status,
     * so a customer can always release a site; idempotent for a domain
     * that holds no slot.
     */
    public function deactivate(string $key, string $domain, ?int $productId = null): License
    {
        return DB::transaction(function () use ($key, $domain, $productId) {
            $license = $this->find($key, $productId, lock: true);

            if (! $license->hasActivation($domain)) {
                return $license;
            }

            $this->saveDomains($license, array_values(array_diff($license->activatedDomains(), [$domain])));

            ActivityLog::record('license.deactivated', $license->user, [
                'license_id' => $license->id,
                'domain' => $domain,
            ]);

            return $license;
        });
    }

    /**
     * Confirm the license is usable and $domain holds one of its slots.
     */
    public function validate(string $key, string $domain, ?int $productId = null): License
    {
        $license = $this->find($key, $productId);
        $this->ensureUsable($license);

        if (! $license->hasActivation($domain)) {
            throw LicenseActivationException::notActivated();
        }

        return $license;
    }

    private function find(string $key, ?int $productId, bool $lock = false): License
    {
        // Keys are generated upper-case; accept any casing from clients.
        $key = strtoupper(trim($key));

        $license = null;
        if ($this->tenantContext->hasTenant()) {
            $query = License::query()->where('license_key', $key);
            $license = ($lock ? $query->lockForUpdate() : $query)->first();
        }

        // Exact comparison on top of the indexed lookup: MySQL's default
        // collation is case- and accent-insensitive, so the WHERE alone
        // can match a key that differs from the stored one.
        $matches = hash_equals($license?->license_key ?? self::MISS_SENTINEL, $key);

        if (! $license || ! $matches || ($productId !== null && $license->product_id !== $productId)) {
            throw LicenseActivationException::invalid();
        }

        return $license;
    }

    private function ensureUsable(License $license): void
    {
        if ($license->isRevoked()) {
            throw LicenseActivationException::revoked();
        }

        if ($license->isExpired()) {
            throw LicenseActivationException::expired();
        }
    }

    /**
     * @param  list<string>  $domains
     */
    private function saveDomains(License $license, array $domains): void
    {
        $license->forceFill([
            'activated_domains' => $domains,
            'activations_count' => count($domains),
        ])->save();
    }
}
