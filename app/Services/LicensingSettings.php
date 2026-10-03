<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Store-wide Extended License rule, set in Admin → Licensing.
 *
 * When enabled, every product that can be licensed offers an Extended
 * License at its regular price × the multiplier, unless the product has
 * its own Extended price (which always wins). Subscriptions never offer
 * one — they're billed by plan.
 */
class LicensingSettings
{
    public const DEFAULT_MULTIPLIER = 5.0;

    private const PREFIX = 'licensing.';

    /**
     * @return array{extended_enabled: bool, extended_multiplier: float}
     */
    public function forForm(): array
    {
        try {
            return [
                'extended_enabled' => (bool) Setting::get(self::PREFIX.'extended_enabled', false),
                'extended_multiplier' => (float) Setting::get(self::PREFIX.'extended_multiplier', self::DEFAULT_MULTIPLIER),
            ];
        } catch (\Throwable) {
            // settings table not migrated yet
            return ['extended_enabled' => false, 'extended_multiplier' => self::DEFAULT_MULTIPLIER];
        }
    }

    /**
     * @param  array{extended_enabled: bool, extended_multiplier: float|string}  $data
     */
    public function save(array $data): void
    {
        Setting::put(self::PREFIX.'extended_enabled', (bool) $data['extended_enabled'], 'bool');
        Setting::put(self::PREFIX.'extended_multiplier', (string) round((float) $data['extended_multiplier'], 2));
    }

    /**
     * The default Extended License price for a regular price, or null when
     * the store-wide rule is off.
     */
    public function defaultExtendedPrice(float|string $regularPrice): ?float
    {
        $settings = $this->forForm();

        if (! $settings['extended_enabled'] || $settings['extended_multiplier'] <= 0) {
            return null;
        }

        return round((float) $regularPrice * $settings['extended_multiplier'], 2);
    }
}
