<?php

namespace App\Domain\AppLicense;

use Illuminate\Support\Str;

/**
 * Signs requests exactly as IndependentLicenseServer verifies them
 * (its App\Licensing\RequestSigning\RequestSignature):
 *
 *   METHOD "\n" PATH "\n" TIMESTAMP "\n" NONCE "\n" hex(sha256(RAW_BODY))
 *
 * PATH is the API path relative to the server's base URL, e.g.
 * /api/v1/licenses/verify — so a server hosted under a sub-path still
 * verifies the same string.
 */
final class RequestSigner
{
    /** @return array<string, string> headers to send */
    public static function headers(InstallationIdentity $identity, string $activationId, string $method, string $path, string $body): array
    {
        $timestamp = (string) now()->getTimestamp();
        $nonce = Str::random(32);

        $canonical = implode("\n", [strtoupper($method), $path, $timestamp, $nonce, hash('sha256', $body)]);

        return [
            'X-Activation-Id' => $activationId,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => rtrim(strtr(base64_encode($identity->sign($canonical)), '+/', '-_'), '='),
        ];
    }
}
