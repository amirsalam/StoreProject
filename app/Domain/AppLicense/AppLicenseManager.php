<?php

namespace App\Domain\AppLicense;

use Illuminate\Support\Facades\Log;

/**
 * Activation and deactivation of THIS installation (admin actions).
 *
 * Signed-token verification and runtime enforcement come in later steps;
 * the state stored here is what they will build on.
 */
class AppLicenseManager
{
    public function __construct(
        private readonly AppLicenseClient $client,
        private readonly AppLicenseStateRepository $state,
    ) {}

    public function status(): AppLicenseStatus
    {
        return AppLicenseStatus::from($this->state->current(), InstallationUrl::domain());
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

        Log::info('Installation license deactivated', ['activation' => $current->activation_id]);
    }

    private function recordFailure(LicenseServerException $e, string $action): void
    {
        if ($this->state->current()) {
            $this->state->update(['last_checked_at' => now(), 'last_error_code' => $e->errorCode]);
        }

        Log::warning("Installation license {$action} failed", ['code' => $e->errorCode, 'status' => $e->httpStatus]);
    }
}
