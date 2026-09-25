<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key-value settings store. Use the static helpers — they cache reads
 * and bust the cache on writes.
 *
 * Setting::get('site.title', 'StoreProject')
 * Setting::put('site.title', 'Acme')
 * Setting::forget('site.logo_path')
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    private const CACHE_KEY = 'settings.all';

    /**
     * Get a value by key, returning $default if missing.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::allCached();
        return $all[$key] ?? $default;
    }

    /**
     * Persist a value. Casts according to $type.
     */
    public static function put(string $key, mixed $value, string $type = 'string'): void
    {
        $stored = match ($type) {
            'json' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            'int' => (string) (int) $value,
            default => (string) ($value ?? ''),
        };

        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public static function forget(string $key): void
    {
        self::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Return all settings as a [key => decoded value] map, cached.
     *
     * Named `allCached` (not `all`) to avoid colliding with Eloquent's
     * `Model::all($columns = ['*'])` signature.
     *
     * @return array<string, mixed>
     */
    public static function allCached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::query()
                ->get(['key', 'value', 'type'])
                ->mapWithKeys(fn (Setting $row) => [$row->key => self::cast($row->value, $row->type)])
                ->all();
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'json' => json_decode($value, true),
            'bool' => $value === '1' || $value === 'true',
            'int' => (int) $value,
            default => $value,
        };
    }
}
