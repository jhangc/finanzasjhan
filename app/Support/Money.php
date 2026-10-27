<?php

namespace App\Support;

/**
 * Aritmética y formato de montos como strings decimales (bcmath).
 * Nunca se convierte un monto a float.
 */
class Money
{
    public const SCALE = 2;

    public const SYMBOLS = ['PEN' => 'S/', 'USD' => '$'];

    /** Normaliza cualquier entrada numérica a "1234.50". */
    public static function of(string|int|float|null $amount): string
    {
        return self::round(self::raw($amount));
    }

    public static function add(string|int|float|null ...$amounts): string
    {
        $total = '0';

        foreach ($amounts as $amount) {
            $total = bcadd($total, self::raw($amount), 6);
        }

        return self::round($total);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::round(bcsub(self::raw($a), self::raw($b), 6));
    }

    public static function mul(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::round(bcmul(self::raw($a), self::raw($b), 6));
    }

    /** División redondeada a 2 decimales; dividir entre cero devuelve 0.00. */
    public static function div(string|int|float|null $a, string|int|float|null $b): string
    {
        if (bccomp(self::raw($b), '0', 6) === 0) {
            return '0.00';
        }

        return self::round(bcdiv(self::raw($a), self::raw($b), 6));
    }

    /** @param  iterable<string|int|float|null>  $amounts */
    public static function sum(iterable $amounts): string
    {
        $total = '0';

        foreach ($amounts as $amount) {
            $total = bcadd($total, self::raw($amount), 6);
        }

        return self::round($total);
    }

    public static function cmp(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::raw($a), self::raw($b), self::SCALE);
    }

    public static function max(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::cmp($a, $b) >= 0 ? self::of($a) : self::of($b);
    }

    /** max(0, $amount) */
    public static function positive(string|int|float|null $amount): string
    {
        return self::max($amount, '0');
    }

    public static function isNegative(string|int|float|null $amount): bool
    {
        return self::cmp($amount, '0') < 0;
    }

    public static function isZero(string|int|float|null $amount): bool
    {
        return self::cmp($amount, '0') === 0;
    }

    /** Porcentaje entero de $part sobre $whole (sin tope). */
    public static function percent(string|int|float|null $part, string|int|float|null $whole): int
    {
        if (self::cmp($whole, '0') <= 0) {
            return self::cmp($part, '0') > 0 ? 100 : 0;
        }

        return (int) bcdiv(bcmul(self::raw($part), '100', 6), self::raw($whole), 0);
    }

    /** Convierte a céntimos enteros (útil para JS sin floats). */
    public static function toCents(string|int|float|null $amount): int
    {
        return (int) bcmul(self::of($amount), '100', 0);
    }

    /** "1250.5" → "S/ 1,250.50" */
    public static function format(string|int|float|null $amount, string $currency = 'PEN'): string
    {
        $value = self::of($amount);
        $negative = str_starts_with($value, '-');
        [$integer, $decimals] = explode('.', ltrim($value, '-'));

        $grouped = strrev(implode(',', str_split(strrev($integer), 3)));
        $symbol = self::SYMBOLS[$currency] ?? $currency;

        return ($negative ? '-' : '').$symbol.' '.$grouped.'.'.$decimals;
    }

    /** Redondeo half-up a 2 decimales. */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $offset = '0.'.str_repeat('0', $scale).'5';
        $rounded = str_starts_with($value, '-')
            ? bcsub($value, $offset, $scale)
            : bcadd($value, $offset, $scale);

        // Evita "-0.00".
        return bccomp($rounded, '0', $scale) === 0 ? bcadd('0', '0', $scale) : $rounded;
    }

    /**
     * Algunos drivers (SQLite) devuelven agregados como float: se convierten
     * a string una sola vez aquí, sin notación científica.
     */
    private static function raw(string|int|float|null $amount): string
    {
        $value = is_float($amount)
            ? sprintf('%.6F', $amount)
            : trim((string) ($amount ?? ''));

        return $value !== '' && is_numeric($value) && ! str_contains(strtolower($value), 'e') ? $value : '0';
    }
}
