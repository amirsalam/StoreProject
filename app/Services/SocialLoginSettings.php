<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * "Continue with Google / GitHub" credentials, configured from
 * Admin → Social login instead of .env.
 *
 * Per provider: an on/off switch, the OAuth client ID and the client
 * secret (encrypted with the app key, never sent back to the browser).
 * Values saved here override GOOGLE_* / GITHUB_* from .env; a provider
 * with nothing saved keeps using .env. A provider is offered on the
 * login and register pages only when it is switched on and has both an
 * ID and a secret — so a half-configured provider never shows a button
 * that fails.
 */
class SocialLoginSettings
{
    public const PROVIDERS = SocialiteService::SUPPORTED;

    private const PREFIX = 'social.';

    /**
     * Current settings for the admin form — never the secrets themselves.
     *
     * @return array<string, array{enabled: bool, client_id: string, has_secret: bool, from_env: bool, callback_url: string}>
     */
    public function forForm(): array
    {
        $out = [];
        foreach (self::PROVIDERS as $provider) {
            $out[$provider] = [
                'enabled' => $this->enabled($provider),
                'client_id' => (string) Setting::get($this->key($provider, 'client_id'), ''),
                'has_secret' => filled(Setting::get($this->key($provider, 'client_secret'))),
                // .env already holds credentials the form would fall back to.
                'from_env' => filled(config("services.$provider.client_id")) && filled(config("services.$provider.client_secret")),
                'callback_url' => $this->callbackUrl($provider),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{enabled?: bool, client_id?: ?string, client_secret?: ?string}>  $data
     */
    public function save(array $data): void
    {
        foreach (self::PROVIDERS as $provider) {
            $values = $data[$provider] ?? [];
            Setting::put($this->key($provider, 'enabled'), (bool) ($values['enabled'] ?? false), 'bool');
            Setting::put($this->key($provider, 'client_id'), trim((string) ($values['client_id'] ?? '')));

            // A blank secret on save keeps the stored one.
            if (filled($values['client_secret'] ?? null)) {
                Setting::put($this->key($provider, 'client_secret'), Crypt::encryptString(trim((string) $values['client_secret'])));
            }
        }
    }

    /**
     * Providers that can be used right now, in display order.
     *
     * @return list<string>
     */
    public function available(): array
    {
        try {
            return array_values(array_filter(self::PROVIDERS, fn (string $p) => $this->isAvailable($p)));
        } catch (\Throwable) {
            return []; // settings table not migrated yet
        }
    }

    public function isAvailable(string $provider): bool
    {
        if (! in_array($provider, self::PROVIDERS, true) || ! $this->enabled($provider)) {
            return false;
        }
        [$id, $secret] = $this->credentials($provider);

        return $id !== '' && $secret !== '';
    }

    /**
     * Point config('services.{provider}') at the saved credentials, so
     * Socialite builds its drivers with them (runs when Socialite is first
     * resolved — see AppServiceProvider). Only saved values are written;
     * anything left empty keeps its .env value. Safe before migrations.
     */
    public function apply(): void
    {
        try {
            foreach (self::PROVIDERS as $provider) {
                [$id, $secret] = $this->credentials($provider);
                config([
                    "services.$provider.client_id" => $id !== '' ? $id : null,
                    "services.$provider.client_secret" => $secret !== '' ? $secret : null,
                    "services.$provider.redirect" => $this->callbackUrl($provider),
                ]);
            }
        } catch (\Throwable) {
            // settings table not migrated yet — .env stays in charge
        }
    }

    /**
     * The address to register as the "Authorized redirect URI" (Google)
     * or "Authorization callback URL" (GitHub).
     */
    public function callbackUrl(string $provider): string
    {
        return url("/auth/$provider/callback");
    }

    /**
     * Client ID and secret: saved values, else .env (config). apply()
     * writes these same values back into config, so this stays correct
     * after it runs.
     *
     * @return array{0: string, 1: string}
     */
    private function credentials(string $provider): array
    {
        $id = trim((string) Setting::get($this->key($provider, 'client_id'), ''));
        $stored = Setting::get($this->key($provider, 'client_secret'));
        $secret = filled($stored) ? trim((string) $this->decrypt((string) $stored)) : '';

        return [
            $id !== '' ? $id : trim((string) config("services.$provider.client_id")),
            $secret !== '' ? $secret : trim((string) config("services.$provider.client_secret")),
        ];
    }

    private function enabled(string $provider): bool
    {
        $saved = Setting::get($this->key($provider, 'enabled'));
        if ($saved !== null) {
            return (bool) $saved;
        }

        // Never saved: on only if .env already has credentials, so those
        // keep working and an empty provider doesn't start switched on.
        return filled(config("services.$provider.client_id")) && filled(config("services.$provider.client_secret"));
    }

    private function key(string $provider, string $field): string
    {
        return self::PREFIX."$provider.$field";
    }

    private function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null; // APP_KEY changed since it was saved — re-enter it
        }
    }
}
