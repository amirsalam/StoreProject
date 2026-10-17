<?php

namespace App\Domain\AppLicense;

/**
 * The license server public keys this installation trusts (kid => raw
 * 32-byte key). Only from shipped config — never from the network.
 * Development keys count only in local/testing.
 */
final class PublicKeyring
{
    /** @return array<string, string> */
    public static function trusted(): array
    {
        $keys = (array) config('app_license.public_keys', []);

        if (app()->environment(['local', 'testing'])) {
            $keys += (array) config('app_license.development_public_keys', []);
        }

        $trusted = [];
        foreach ($keys as $kid => $encoded) {
            $raw = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (is_string($kid) && $raw !== false && strlen($raw) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $trusted[$kid] = $raw;
            }
        }

        return $trusted;
    }
}
