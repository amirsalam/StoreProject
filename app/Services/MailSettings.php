<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Outgoing email (SMTP) configured from Admin → Email instead of .env.
 *
 * Stored in the platform-wide `settings` table; the password is encrypted
 * with the app key and never sent back to the browser. When enabled, the
 * saved values override config('mail.*') the first time the mailer is used
 * (see AppServiceProvider), so every email — order confirmations,
 * invitations, contact alerts — goes out through this SMTP server. When
 * disabled, .env (MAIL_*) applies as before.
 */
class MailSettings
{
    public const ENCRYPTIONS = ['tls', 'ssl', 'none'];

    private const PREFIX = 'mail.';

    /**
     * Current settings for the admin form — never the password itself.
     *
     * @return array{enabled: bool, host: string, port: int, encryption: string, username: string, has_password: bool, from_address: string, from_name: string}
     */
    public function forForm(): array
    {
        return [
            'enabled' => (bool) Setting::get(self::PREFIX.'enabled', false),
            'host' => (string) Setting::get(self::PREFIX.'host', ''),
            'port' => (int) Setting::get(self::PREFIX.'port', 587),
            'encryption' => (string) Setting::get(self::PREFIX.'encryption', 'tls'),
            'username' => (string) Setting::get(self::PREFIX.'username', ''),
            'has_password' => filled(Setting::get(self::PREFIX.'password')),
            'from_address' => (string) Setting::get(self::PREFIX.'from_address', ''),
            'from_name' => (string) Setting::get(self::PREFIX.'from_name', ''),
        ];
    }

    /**
     * @param  array{enabled: bool, host?: ?string, port?: ?int, encryption?: ?string, username?: ?string, password?: ?string, from_address?: ?string, from_name?: ?string}  $data
     */
    public function save(array $data): void
    {
        Setting::put(self::PREFIX.'enabled', $data['enabled'], 'bool');
        Setting::put(self::PREFIX.'host', trim((string) ($data['host'] ?? '')));
        Setting::put(self::PREFIX.'port', (int) ($data['port'] ?? 587), 'int');
        Setting::put(self::PREFIX.'encryption', (string) ($data['encryption'] ?? 'tls'));
        Setting::put(self::PREFIX.'username', trim((string) ($data['username'] ?? '')));
        Setting::put(self::PREFIX.'from_address', trim((string) ($data['from_address'] ?? '')));
        Setting::put(self::PREFIX.'from_name', trim((string) ($data['from_name'] ?? '')));

        // A blank password on save keeps the stored one.
        if (filled($data['password'] ?? null)) {
            Setting::put(self::PREFIX.'password', Crypt::encryptString((string) $data['password']));
        }
    }

    /**
     * Point Laravel's mail config at the saved SMTP server, if enabled and
     * complete. Safe to call before the settings table exists (fresh
     * install, migrations): it then leaves .env in charge.
     */
    public function apply(): void
    {
        try {
            $settings = $this->forForm();
            $password = Setting::get(self::PREFIX.'password');
        } catch (\Throwable) {
            return; // settings table not migrated yet
        }

        if (! $settings['enabled'] || $settings['host'] === '' || $settings['from_address'] === '') {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.scheme' => $settings['encryption'] === 'ssl' ? 'smtps' : null,
            'mail.mailers.smtp.encryption' => $settings['encryption'] === 'none' ? null : $settings['encryption'],
            'mail.mailers.smtp.username' => $settings['username'] !== '' ? $settings['username'] : null,
            'mail.mailers.smtp.password' => filled($password) ? $this->decrypt((string) $password) : null,
            'mail.from.address' => $settings['from_address'],
            'mail.from.name' => $settings['from_name'] !== '' ? $settings['from_name'] : config('mail.from.name'),
        ]);
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
