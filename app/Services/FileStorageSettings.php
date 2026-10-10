<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Cloud storage for product files, configured from Admin → File storage.
 *
 * Every provider is reached through the S3 API, so one form covers AWS S3,
 * Google Cloud Storage (its S3-compatible "interoperability" access with
 * HMAC keys), Cloudflare R2, DigitalOcean Spaces and any S3-compatible
 * service. When enabled, product files are uploaded by the browser
 * straight to the bucket (no PHP upload limit) and downloaded through
 * short-lived signed URLs; when disabled, files stay on the server disk.
 *
 * Stored in the `settings` table; the secret key is encrypted with the app
 * key and never sent back to the browser.
 */
class FileStorageSettings
{
    /** The filesystem disk name product files use when cloud storage is on. */
    public const DISK = 'product_cloud';

    /**
     * provider => [label, default endpoint (":region" is replaced), default region].
     */
    public const PROVIDERS = [
        's3' => ['Amazon S3', null, 'us-east-1'],
        'gcs' => ['Google Cloud Storage', 'https://storage.googleapis.com', 'auto'],
        'r2' => ['Cloudflare R2', null, 'auto'],
        'spaces' => ['DigitalOcean Spaces', 'https://:region.digitaloceanspaces.com', 'fra1'],
        'custom' => ['Other S3-compatible', null, 'us-east-1'],
    ];

    private const PREFIX = 'storage.';

    /**
     * Current settings for the admin form — never the secret itself.
     *
     * @return array{enabled: bool, provider: string, bucket: string, region: string, endpoint: string, key: string, has_secret: bool, path_style: bool}
     */
    public function forForm(): array
    {
        return [
            'enabled' => (bool) Setting::get(self::PREFIX.'enabled', false),
            'provider' => (string) Setting::get(self::PREFIX.'provider', 's3'),
            'bucket' => (string) Setting::get(self::PREFIX.'bucket', ''),
            'region' => (string) Setting::get(self::PREFIX.'region', ''),
            'endpoint' => (string) Setting::get(self::PREFIX.'endpoint', ''),
            'key' => (string) Setting::get(self::PREFIX.'key', ''),
            'has_secret' => filled(Setting::get(self::PREFIX.'secret')),
            'path_style' => (bool) Setting::get(self::PREFIX.'path_style', false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        Setting::put(self::PREFIX.'enabled', (bool) $data['enabled'], 'bool');
        Setting::put(self::PREFIX.'provider', (string) ($data['provider'] ?? 's3'));
        Setting::put(self::PREFIX.'bucket', trim((string) ($data['bucket'] ?? '')));
        Setting::put(self::PREFIX.'region', trim((string) ($data['region'] ?? '')));
        Setting::put(self::PREFIX.'endpoint', rtrim(trim((string) ($data['endpoint'] ?? '')), '/'));
        Setting::put(self::PREFIX.'key', trim((string) ($data['key'] ?? '')));
        Setting::put(self::PREFIX.'path_style', (bool) ($data['path_style'] ?? false), 'bool');

        // A blank secret on save keeps the stored one.
        if (filled($data['secret'] ?? null)) {
            Setting::put(self::PREFIX.'secret', Crypt::encryptString(trim((string) $data['secret'])));
        }

        // Rebuild the disk from the new values on next use.
        config(['filesystems.disks.'.self::DISK => null]);
        Storage::forgetDisk(self::DISK);
    }

    /**
     * Whether product files go to the cloud: enabled and complete.
     */
    public function enabled(): bool
    {
        return $this->diskConfig() !== null;
    }

    /**
     * The S3 disk config built from the saved settings, or null when cloud
     * storage is off or incomplete. Safe before migrations have run.
     *
     * @return array<string, mixed>|null
     */
    public function diskConfig(): ?array
    {
        try {
            $s = $this->forForm();
            $encrypted = Setting::get(self::PREFIX.'secret');
        } catch (\Throwable) {
            return null; // settings table not migrated yet
        }

        if (! $s['enabled'] || $s['bucket'] === '' || $s['key'] === '' || blank($encrypted)) {
            return null;
        }

        try {
            $secret = Crypt::decryptString((string) $encrypted);
        } catch (\Throwable) {
            return null; // app key changed since it was saved
        }

        [, $defaultEndpoint, $defaultRegion] = self::PROVIDERS[$s['provider']] ?? self::PROVIDERS['custom'];
        $region = $s['region'] !== '' ? $s['region'] : $defaultRegion;
        $endpoint = $s['endpoint'] !== '' ? $s['endpoint'] : ($defaultEndpoint ? str_replace(':region', $region, $defaultEndpoint) : null);

        return array_filter([
            'driver' => 's3',
            'key' => $s['key'],
            'secret' => $secret,
            'region' => $region,
            'bucket' => $s['bucket'],
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => $s['path_style'],
            'visibility' => 'private',
            'throw' => true,
            // Only send checksums S3 requires: Google Cloud Storage's S3
            // interoperability (and some other services) reject the
            // CRC32 headers newer SDKs add by default.
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
        ], fn ($v) => $v !== null);
    }

    /**
     * Register the cloud disk under {@see self::DISK} (once per request).
     * Returns false when cloud storage is not configured.
     */
    public function apply(): bool
    {
        if (config('filesystems.disks.'.self::DISK)) {
            return true;
        }

        $config = $this->diskConfig();
        if ($config === null) {
            return false;
        }

        config(['filesystems.disks.'.self::DISK => $config]);

        return true;
    }

    /**
     * The browser CORS rules the bucket needs so pages on this site can
     * upload straight to it — shown on the settings page to paste in.
     *
     * @return array{s3: string, gcs: string}
     */
    public static function corsRules(string $origin): array
    {
        return [
            's3' => json_encode([[
                'AllowedOrigins' => [$origin],
                'AllowedMethods' => ['PUT', 'GET'],
                'AllowedHeaders' => ['*'],
                'ExposeHeaders' => ['ETag'],
                'MaxAgeSeconds' => 3600,
            ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'gcs' => json_encode([[
                'origin' => [$origin],
                'method' => ['PUT', 'GET'],
                'responseHeader' => ['Content-Type', 'ETag'],
                'maxAgeSeconds' => 3600,
            ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }
}
