<?php

namespace App\Domain\AppLicense;

use App\Models\AppLicenseState;
use Illuminate\Contracts\Encryption\DecryptException;
use RuntimeException;

/**
 * The single app_license_state row. Not tenant scoped and not cached:
 * license state must always be read fresh.
 */
class AppLicenseStateRepository
{
    public function current(): ?AppLicenseState
    {
        return AppLicenseState::query()->orderBy('id')->first();
    }

    /** The stored identity, creating one on first use. */
    public function identity(): InstallationIdentity
    {
        $state = $this->current() ?? $this->store(InstallationIdentity::generate());

        return $this->toIdentity($state);
    }

    /**
     * Start over with a brand-new identity and no activation — used when
     * the installation moved to a different domain (e.g. a copied database)
     * so it never reuses the original's activation.
     */
    public function resetIdentity(): InstallationIdentity
    {
        AppLicenseState::query()->delete();

        return $this->toIdentity($this->store(InstallationIdentity::generate()));
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes): AppLicenseState
    {
        $state = $this->current() ?? throw new RuntimeException('No application license state exists yet.');
        $state->fill($attributes)->save();

        return $state;
    }

    private function store(InstallationIdentity $identity): AppLicenseState
    {
        return AppLicenseState::create([
            'installation_id' => $identity->installationId,
            'public_key' => $identity->publicKey,
            'secret_key' => $identity->exportSecretKey(),
        ]);
    }

    private function toIdentity(AppLicenseState $state): InstallationIdentity
    {
        try {
            $secret = base64_decode($state->secret_key, true);
        } catch (DecryptException) {
            $secret = false;
        }

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('The stored installation key is unreadable (was APP_KEY changed?).');
        }

        return new InstallationIdentity($state->installation_id, $state->public_key, $secret);
    }
}
