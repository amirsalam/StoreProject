<?php

namespace App\Support;

/**
 * Money conversion helpers.
 *
 * Storage convention: BIGINT cents (no float, ever, for ledger math).
 * Display / API boundary: decimal strings.
 */
final class Money
{
    /**
     * '49.00' or 49.00 → 4900 cents. Caller is responsible for any
     * locale formatting; this only handles the dot-decimal canonical form.
     */
    public static function toCents(string|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Decimals a currency uses (ISO 4217, config/currencies.php): JPY 0, USD 2, KWD 3. */
    public static function minorUnits(string $currency): int
    {
        return (int) (config('currencies.'.strtoupper($currency))[1] ?? 2);
    }

    /**
     * Amount in the currency's smallest unit, as payment providers expect:
     * 49.00 USD → 4900, 5000 JPY → 5000, 1.250 KWD → 1250.
     */
    public static function toMinor(string|float $amount, string $currency): int
    {
        return (int) round(((float) $amount) * (10 ** self::minorUnits($currency)));
    }

    /**
     * An amount as shown to people: "$49.00", "49,00 €", "¥5,000" — in the
     * current app locale when the intl extension is available.
     */
    public static function format(string|float $amount, string $currency, ?string $locale = null): string
    {
        $currency = strtoupper($currency);

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale ?? app()->getLocale(), \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency((float) $amount, $currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $currency.' '.number_format((float) $amount, self::minorUnits($currency), '.', ',');
    }

    /** Whether products can be priced in this currency. */
    public static function isSupported(string $currency): bool
    {
        return array_key_exists(strtoupper($currency), config('currencies', []));
    }

    /**
     * 4900 → '49.00'. minorUnits handles ISO currencies with 0 or 3
     * decimal places (JPY=0, KWD=3) — most callers use the default of 2.
     */
    public static function fromCents(int $cents, int $minorUnits = 2): string
    {
        return number_format($cents / (10 ** $minorUnits), $minorUnits, '.', '');
    }
}
