<?php

namespace App\Domain\AppLicense;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Activation and deactivation of THIS installation (admin actions).
 * A token is only stored after AppLicenseTokenVerifier accepted it.
 */
class AppLicenseManager
{
    /** Definitive answers about the LICENSE itself: unlicensed at once. */
    public const LICENSE_REFUSALS = ['license_suspended', 'license_revoked', 'license_expired'];

    public function __construct(
        private readonly AppLicenseClient $client,
        private readonly AppLicenseStateRepository $state,
        private readonly AppLicenseTokenVerifier $verifier,
        private readonly AppLicenseEvaluator $evaluator,
    ) {}

    public function status(): AppLicenseStatus
    {
        return AppLicenseStatus::from($this->state->current(), InstallationUrl::domain(), $this->evaluator->evaluate());
    }

    /**
     * @throws LicenseServerException
     */
    public function activate(#[\SensitiveParameter] string $licenseKey): void
    {
        $domain = InstallationUrl::domain()
            ?? throw new LicenseServerException('app_url_invalid', 'APP_URL is not a valid URL.');

        $current = $this->state->current();

        if ($current?->activation_id && $current->bound_domain === $domain) {
            throw new LicenseServerException('already_activated', 'This installation is already activated.');
        }

        // Bound to another domain (moved server, copied database): start over
        // with a new identity so the original's activation is never reused.
        $identity = $current?->bound_domain && $current->bound_domain !== $domain
            ? $this->state->resetIdentity()
            : $this->state->identity();

        try {
            $result = $this->client->activate($licenseKey, InstallationUrl::url(), $identity);
        } catch (LicenseServerException $e) {
            $this->recordFailure($e, 'activation');

            throw $e;
        }

        // Never store an answer we cannot verify (wrong server, wrong keys,
        // tampering): the installation stays unactivated.
        try {
            $this->verifier->verify($result->token, $domain, $identity->installationId, (string) $result->activationId);
        } catch (InvalidLicenseToken $e) {
            $failure = new LicenseServerException('token_invalid', "The license server's token was rejected ({$e->reason}).");
            $this->recordFailure($failure, 'activation');
            Log::error('Installation license token rejected at activation', ['reason' => $e->reason]);

            throw $failure;
        }

        $this->state->update([
            'activation_id' => $result->activationId,
            'bound_domain' => $result->domain,
            'environment' => $result->environment,
            'license_status' => $result->licenseStatus,
            'token' => $result->token,
            'token_expires_at' => $result->tokenExpiresAt,
            'last_checked_at' => now(),
            'last_success_at' => now(),
            'last_error_code' => null,
        ]);

        $this->evaluator->forget();
        Log::info('Installation license activated', ['activation' => $result->activationId, 'domain' => $result->domain]);
    }

    /**
     * Release this installation on the server, then forget it locally.
     *
     * @throws LicenseServerException when the server could not confirm it
     *                                (state is kept, so it can be retried)
     */
    public function deactivate(): void
    {
        $current = $this->state->current();
        if (! $current?->activation_id) {
            return;
        }

        try {
            $this->client->deactivate($current->activation_id, $this->state->identity());
        } catch (LicenseServerException $e) {
            // The server already forgot this activation: nothing to release.
            if ($e->errorCode !== 'activation_not_found') {
                $this->recordFailure($e, 'deactivation');

                throw $e;
            }
        }

        $this->state->update([
            'activation_id' => null,
            'bound_domain' => null,
            'environment' => null,
            'license_status' => null,
            'token' => null,
            'token_expires_at' => null,
            'last_checked_at' => now(),
            'last_error_code' => null,
        ]);

        $this->evaluator->forget();
        Log::info('Installation license deactivated', ['activation' => $current->activation_id]);
    }

    /**
     * Periodic check-in with the license server (scheduled, opportunistic, or
     * "Check now"). Never throws for server trouble; returns what happened:
     *
     *   refreshed        new verified token stored (also how a reinstated
     *                    license recovers)
     *   refused          the server said no to the LICENSE (suspended /
     *                    revoked / expired): unlicensed now, activation kept
     *   released         the server no longer knows this activation (released
     *                    by an admin, replaced): activation cleared
     *   failed           temporary trouble (network, 5xx, rate limit, clock,
     *                    unverifiable answer): current token kept → grace
     *   not_activated / domain_changed   nothing to check, no request made
     */
    public function refresh(): string
    {
        $current = $this->state->current();
        if (! $current?->activation_id) {
            return 'not_activated';
        }

        $domain = InstallationUrl::domain();
        if ($domain === null || $current->bound_domain !== $domain) {
            return 'domain_changed';
        }

        $identity = $this->state->identity();

        try {
            $result = $this->client->verify($current->activation_id, InstallationUrl::url(), $identity);
            $this->verifier->verify($result->token, $domain, $identity->installationId, $current->activation_id);
        } catch (InvalidLicenseToken $e) {
            // Not a verdict about the license: keep the current token.
            return $this->checkInFailed(new LicenseServerException('token_invalid', "Unverifiable token ({$e->reason})."));
        } catch (LicenseServerException $e) {
            return $this->checkInFailed($e);
        }

        $this->state->update([
            'token' => $result->token,
            'token_expires_at' => $result->tokenExpiresAt,
            'license_status' => $result->licenseStatus,
            'last_checked_at' => now(),
            'last_success_at' => now(),
            'last_error_code' => null,
        ]);
        $this->evaluator->forget();

        return 'refreshed';
    }

    /**
     * Due when activated, not checked within the last hour, and either the
     * last success is older than 20 h or the token expires within 24 h.
     * So: about daily normally, hourly retries after a failure.
     */
    public function isRefreshDue(): bool
    {
        $s = $this->state->current();
        if (! $s?->activation_id) {
            return false;
        }

        if ($s->last_checked_at !== null && $s->last_checked_at->greaterThan(now()->subHour())) {
            return false;
        }

        return $s->last_success_at === null
            || $s->last_success_at->lessThan(now()->subHours(20))
            || $s->token_expires_at === null
            || $s->token_expires_at->lessThan(now()->addDay());
    }

    /** refresh() when due, with a lock so concurrent triggers make one call. */
    public function refreshIfDue(): ?string
    {
        if (! $this->isRefreshDue()) {
            return null;
        }

        $lock = Cache::lock('app-license:refresh', 120);
        if (! $lock->get()) {
            return null;
        }

        try {
            return $this->isRefreshDue() ? $this->refresh() : null;
        } finally {
            $lock->release();
        }
    }

    private function checkInFailed(LicenseServerException $e): string
    {
        if (in_array($e->errorCode, ['activation_not_found', 'installation_mismatch'], true)) {
            $this->state->update([
                'activation_id' => null,
                'bound_domain' => null,
                'environment' => null,
                'license_status' => null,
                'token' => null,
                'token_expires_at' => null,
                'last_checked_at' => now(),
                'last_error_code' => $e->errorCode,
            ]);
            $outcome = 'released';
        } else {
            $this->state->update(['last_checked_at' => now(), 'last_error_code' => $e->errorCode]);
            $outcome = in_array($e->errorCode, self::LICENSE_REFUSALS, true) ? 'refused' : 'failed';
        }

        $this->evaluator->forget();
        Log::log($outcome === 'failed' ? 'warning' : 'error', 'Installation license check-in: '.$outcome, [
            'code' => $e->errorCode,
            'status' => $e->httpStatus,
        ]);

        return $outcome;
    }

    private function recordFailure(LicenseServerException $e, string $action): void
    {
        if ($this->state->current()) {
            $this->state->update(['last_checked_at' => now(), 'last_error_code' => $e->errorCode]);
        }

        Log::warning("Installation license {$action} failed", ['code' => $e->errorCode, 'status' => $e->httpStatus]);
    }
}
