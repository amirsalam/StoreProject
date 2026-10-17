<?php

namespace App\Domain\AppLicense;

/**
 * The address this installation is licensed for: APP_URL — the central
 * domain — never the request host, so tenant subdomains and custom
 * domains never count as extra installations.
 *
 * domain() mirrors the server's canonical form closely enough to notice a
 * move (lowercase, no trailing dot, punycode, no leading "www."); the
 * server's own answer is what gets stored as bound_domain.
 */
final class InstallationUrl
{
    public static function url(): string
    {
        return (string) config('app.url');
    }

    public static function domain(): ?string
    {
        $host = parse_url(self::url(), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = rtrim(mb_strtolower(trim($host, '[]'), 'UTF-8'), '.');
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) {
                return null;
            }
            $host = strtolower($ascii);
        }

        if (str_starts_with($host, 'www.') && substr_count($host, '.') >= 2) {
            $host = substr($host, 4);
        }

        return $host;
    }
}
