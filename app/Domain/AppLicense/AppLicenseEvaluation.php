<?php

namespace App\Domain\AppLicense;

use Carbon\CarbonImmutable;

/**
 * The single answer to "may this installation run right now?".
 *
 *   not_activated  no activation / token stored
 *   licensed       valid token, refresh deadline (exp) not reached
 *   grace          valid token past exp but before the grace deadline:
 *                  keep working while the license server is unreachable
 *   unlicensed     invalid token, license not active, license expired,
 *                  or grace over — `reason` says which
 */
final class AppLicenseEvaluation
{
    public const NOT_ACTIVATED = 'not_activated';

    public const LICENSED = 'licensed';

    public const GRACE = 'grace';

    public const UNLICENSED = 'unlicensed';

    public function __construct(
        public readonly string $state,
        public readonly ?string $reason = null,
        public readonly ?VerifiedLicenseToken $token = null,
        public readonly ?CarbonImmutable $graceEndsAt = null,
    ) {}

    /** May the protected parts of the app run? */
    public function allowsUse(): bool
    {
        return $this->state === self::LICENSED || $this->state === self::GRACE;
    }
}
