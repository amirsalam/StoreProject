<?php

namespace App\Domain\AppLicense;

use App\Models\AppLicenseState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Decides, from local state only (no network), whether this installation
 * is licensed right now. Cheap enough to run per request: one row read
 * and one Ed25519 verification, memoized for the rest of the request.
 */
class AppLicenseEvaluator
{
    private ?AppLicenseEvaluation $memo = null;

    public function __construct(
        private readonly AppLicenseStateRepository $state,
        private readonly AppLicenseTokenVerifier $verifier,
    ) {}

    public function evaluate(): AppLicenseEvaluation
    {
        return $this->memo ??= $this->compute($this->state->current());
    }

    /** Drop the per-request memo (after activation, deactivation, refresh). */
    public function forget(): void
    {
        $this->memo = null;
    }

    private function compute(?AppLicenseState $state): AppLicenseEvaluation
    {
        if (! $state?->activation_id || ! $state->token) {
            return new AppLicenseEvaluation(AppLicenseEvaluation::NOT_ACTIVATED);
        }

        // The server explicitly refused at the last check-in: act now, do not
        // wait for the (still validly signed) older token to run out. Cleared
        // by the next successful check-in (e.g. after a reinstatement).
        if (in_array($state->last_error_code, AppLicenseManager::LICENSE_REFUSALS, true)) {
            return new AppLicenseEvaluation(AppLicenseEvaluation::UNLICENSED, $state->last_error_code);
        }

        $domain = InstallationUrl::domain();
        if ($domain === null) {
            return new AppLicenseEvaluation(AppLicenseEvaluation::UNLICENSED, 'app_url_invalid');
        }

        try {
            $token = $this->verifier->verify($state->token, $domain, $state->installation_id, $state->activation_id);
        } catch (InvalidLicenseToken $e) {
            Log::warning('Stored installation license token rejected', ['reason' => $e->reason]);

            return new AppLicenseEvaluation(AppLicenseEvaluation::UNLICENSED, "token_{$e->reason}");
        }

        $now = CarbonImmutable::now();

        if ($token->licenseStatus !== 'active') {
            return new AppLicenseEvaluation(AppLicenseEvaluation::UNLICENSED, "license_{$token->licenseStatus}", $token);
        }
        if ($token->licenseExpiresAt !== null && $now->greaterThanOrEqualTo($token->licenseExpiresAt)) {
            return new AppLicenseEvaluation(AppLicenseEvaluation::UNLICENSED, 'license_expired', $token);
        }
        if ($now->lessThan($token->expiresAt)) {
            return new AppLicenseEvaluation(AppLicenseEvaluation::LICENSED, null, $token);
        }

        $graceEnds = $this->graceEnd($token);
        if ($now->lessThan($graceEnds)) {
            return new AppLicenseEvaluation(AppLicenseEvaluation::GRACE, 'refresh_overdue', $token, $graceEnds);
        }

        return new AppLicenseEvaluation(AppLicenseEvaluation::UNLICENSED, 'grace_expired', $token, $graceEnds);
    }

    /** The signed grace deadline, optionally shortened (never extended) by config. */
    private function graceEnd(VerifiedLicenseToken $token): CarbonImmutable
    {
        $maxDays = config('app_license.max_grace_days');
        if ($maxDays === null || $maxDays === '') {
            return $token->graceExpiresAt;
        }

        return $token->graceExpiresAt->min($token->expiresAt->addDays(max(0, (int) $maxDays)));
    }
}
