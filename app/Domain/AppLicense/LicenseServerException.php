<?php

namespace App\Domain\AppLicense;

use RuntimeException;

/**
 * A failed call to the license server.
 *
 * definitive  the server answered and refused (license revoked, activation
 *             released, wrong domain…): act on it.
 * otherwise   transient or local trouble (network, 5xx, rate limit, clock
 *             skew, garbled reply, not configured): never treat as a
 *             verdict — the offline grace period covers it (Step 18).
 */
class LicenseServerException extends RuntimeException
{
    /** Server error codes that are real answers about this license/installation. */
    public const DEFINITIVE = [
        'invalid_license',
        'license_suspended',
        'license_revoked',
        'license_expired',
        'domain_invalid',
        'domain_limit_reached',
        'transfer_limit_reached',
        'activation_not_found',
        'installation_mismatch',
        // Not 'invalid_request': that means OUR request was malformed — a bug
        // on this side, never a reason to lock the installation out.
    ];

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isDefinitive(): bool
    {
        return in_array($this->errorCode, self::DEFINITIVE, true);
    }

    public static function notConfigured(string $why): self
    {
        return new self('not_configured', $why);
    }

    public static function network(string $why, ?\Throwable $previous = null): self
    {
        return new self('network_error', "The license server could not be reached: {$why}", null, $previous);
    }

    public static function invalidResponse(int $status): self
    {
        return new self('invalid_response', 'The license server sent an unexpected response.', $status);
    }
}
