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

    /**
     * 4900 → '49.00'. minorUnits handles ISO currencies with 0 or 3
     * decimal places (JPY=0, KWD=3) — most callers use the default of 2.
     */
    public static function fromCents(int $cents, int $minorUnits = 2): string
    {
        return number_format($cents / (10 ** $minorUnits), $minorUnits, '.', '');
    }
}
