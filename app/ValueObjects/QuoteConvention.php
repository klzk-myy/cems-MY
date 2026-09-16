<?php

namespace App\ValueObjects;

use App\Models\Currency;

/**
 * QuoteConvention
 *
 * How a currency's exchange rates are quoted. The single implementation of
 * quoted ↔ per-unit conversion used by every rate consumer (rate cards,
 * history rows, transaction boundaries, seeders, displays):
 *
 *   direct  (inverse=false): quoted = MYR per `unit` foreign units
 *                            e.g. RM 235 per 1,000,000 IDR
 *   inverse (inverse=true):  quoted = foreign units per `unit` MYR
 *                            e.g. RM 1 = 4,255 IDR
 *
 *   toPerUnit:   direct → quoted / unit      inverse → unit / quoted
 *   fromPerUnit: direct → perUnit × unit     inverse → unit / perUnit
 *
 * Normalized per-unit values use scale 8, matching the decimal(18,8) rate
 * columns (transactions.rate, exchange_rates, exchange_rate_histories,
 * currency_positions, revaluation_entries, stock_transfer_items).
 */
final class QuoteConvention
{
    /**
     * Storage precision for normalized per-unit rates — decimal(18,8).
     */
    public const PER_UNIT_SCALE = 8;

    /**
     * Guard digits used while bridging between two conventions so an inverse
     * round-trip (1 / (unit / quoted)) does not accumulate visible error.
     */
    private const INTERMEDIATE_SCALE = 12;

    public function __construct(
        public readonly int $unit = 1,
        public readonly bool $inverse = false,
    ) {}

    /**
     * Convention carried by a currency (or a rate/history row, which snapshots
     * the same columns). Missing model falls back to the unit-1 direct
     * convention, identical to the pre-unit behaviour.
     */
    public static function for(?object $carrier): self
    {
        return new self(
            (int) ($carrier->rate_unit ?? 1),
            (bool) ($carrier->rate_inverse ?? false),
        );
    }

    public static function forCode(string $currencyCode): self
    {
        return self::for(Currency::find($currencyCode));
    }

    /**
     * Convert a rate quoted in this convention to normalized per-unit MYR.
     *
     * @param  string  $quoted  rate in this convention's terms; must be numeric
     * @return numeric-string
     */
    public function toPerUnit(string $quoted, int $scale = self::PER_UNIT_SCALE): string
    {
        /** @var numeric-string $quoted */
        $unit = (string) $this->unit;

        return bcadd(
            $this->inverse
                ? bcdiv($unit, $quoted, $scale + 4)
                : bcdiv($quoted, $unit, $scale + 4),
            '0',
            $scale
        );
    }

    /**
     * Convert a normalized per-unit MYR rate into this convention's quoted
     * terms. Direct yields MYR per unit foreign; inverse yields foreign units
     * per unit MYR.
     *
     * @param  string  $perUnit  normalized per-unit rate; must be numeric
     * @return numeric-string
     */
    public function fromPerUnit(string $perUnit, int $scale = self::PER_UNIT_SCALE): string
    {
        /** @var numeric-string $perUnit */
        $unit = (string) $this->unit;

        return bcadd(
            $this->inverse
                ? bcdiv($unit, $perUnit, $scale + 4)
                : bcmul($perUnit, $unit, $scale + 4),
            '0',
            $scale
        );
    }

    /**
     * Express a rate quoted in this convention in another convention without
     * changing its real value, bridging through per-unit MYR at intermediate
     * precision. Identical conventions short-circuit so a stored card never
     * accumulates re-quoting noise.
     *
     * @param  string  $quoted  rate in this convention's terms; must be numeric
     * @return numeric-string
     */
    public function reQuoteInto(string $quoted, self $to): string
    {
        if ($this == $to) {
            /** @var numeric-string $quoted */
            return $quoted;
        }

        return $to->fromPerUnit($this->toPerUnit($quoted, self::INTERMEDIATE_SCALE));
    }
}
