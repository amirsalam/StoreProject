<?php

namespace App\Domain\AppLicense;

/**
 * Admin-facing explanation for each failure code (English source text,
 * translated through lang/{locale}.json).
 */
final class AppLicenseMessages
{
    public static function for(string $code): string
    {
        return __(match ($code) {
            'invalid_license' => 'This license key is not valid for StoreProject. Check it and try again.',
            'license_suspended' => 'This license is suspended. Please contact support.',
            'license_revoked' => 'This license has been revoked.',
            'license_expired' => 'This license has expired. Renew it to continue.',
            'domain_invalid' => 'This site address (APP_URL) cannot be activated. Set APP_URL to your real domain name.',
            'domain_limit_reached' => 'This license is already active on another domain. Deactivate it there first, or contact support to move it.',
            'transfer_limit_reached' => 'This license has moved domains too often recently. Contact support to move it.',
            'already_activated' => 'This installation is already activated.',
            'app_url_invalid' => 'APP_URL is not a valid address. Fix it in the .env file first.',
            'not_configured' => 'The license server address is not configured (APP_LICENSE_SERVER_URL).',
            'rate_limited' => 'Too many attempts. Please wait a minute and try again.',
            'request_expired' => 'This server’s clock is wrong. Correct the system time and try again.',
            'activation_not_found', 'installation_mismatch' => 'This installation is no longer activated on the license server (it may have been released or replaced). Activate it again.',
            'token_invalid' => 'The license server’s answer could not be verified. Check APP_LICENSE_SERVER_URL, or update StoreProject.',
            default => 'The license server could not be reached. Please try again in a few minutes.',
        });
    }
}
