<?php

namespace App\Domain\Licensing;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * A license API call that cannot be honoured. Renders itself as the
 * public JSON error envelope `{ message, error }` with the matching
 * HTTP status, so controllers never hand-build error responses.
 *
 * invalid() is deliberately static: an unknown key, a key belonging to
 * another tenant or product, and a request with no tenant in context
 * all produce the byte-identical response, so the endpoint can't be
 * used to learn which keys exist.
 */
class LicenseActivationException extends \RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        return new self('invalid_license', 404, 'The license key is invalid.');
    }

    public static function revoked(): self
    {
        return new self('license_revoked', 403, 'This license has been revoked.');
    }

    public static function expired(): self
    {
        return new self('license_expired', 403, 'This license has expired.');
    }

    public static function limitReached(): self
    {
        return new self('activation_limit_reached', 409, 'This license has reached its activation limit.');
    }

    public static function notActivated(): self
    {
        return new self('not_activated', 403, 'This license is not activated for this domain.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->errorCode,
        ], $this->status);
    }
}
