<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP-based 2FA. Wraps pragmarx/google2fa for secret generation and
 * verification, and bacon/bacon-qr-code for inline SVG QR rendering.
 *
 * Lifecycle:
 *   1. enable($user)        → generates secret + recovery codes (pending)
 *   2. confirm($user, $otp) → verifies the user can read the code,
 *                              flips two_factor_confirmed_at
 *   3. disable($user)       → clears everything
 *
 * Login challenge:
 *   verifyTotp($user, $otp) — validates a 6-digit code
 *   consumeRecoveryCode($user, $code) — single-use, removes from list
 */
class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Provision a new secret + recovery codes. The user still has to
     * confirm() with a valid OTP before 2FA actually gates their account.
     */
    public function enable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(),
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Verify the first OTP from the authenticator app and mark 2FA active.
     */
    public function confirm(User $user, string $otp): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $otp)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        return true;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function regenerateRecoveryCodes(User $user): void
    {
        $user->forceFill([
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
        ])->save();
    }

    public function verifyTotp(User $user, string $otp): bool
    {
        if (! $user->two_factor_secret || ! $user->hasTwoFactorEnabled()) {
            return false;
        }
        return $this->google2fa->verifyKey($user->two_factor_secret, $otp);
    }

    /**
     * Consume a recovery code (one-time use). Returns true on success.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = strtolower(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];
        $idx = array_search($code, array_map('strtolower', $codes), true);
        if ($idx === false) {
            return false;
        }

        unset($codes[$idx]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
        return true;
    }

    /**
     * Build the otpauth:// URI for QR rendering and manual entry.
     */
    public function otpauthUri(User $user): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            (string) $user->two_factor_secret,
        );
    }

    /**
     * Render the otpauth URI as an inline SVG (no external endpoint).
     */
    public function qrSvg(User $user): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220, 1),
            new SvgImageBackEnd(),
        );
        $writer = new Writer($renderer);
        return $writer->writeString($this->otpauthUri($user));
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::lower(Str::random(5) . '-' . Str::random(5)))
            ->all();
    }
}
