<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * docs/PRD.md §141 — money is DECIMAL(15,4), and PHP must never use native
 * +, -, *, / on it. Every payroll calculation goes through here: BCMath
 * arithmetic at an internal scale of 6, then a single half-up round to 4
 * places when a value is finalised (round()). Values move as strings
 * everywhere; a float never touches a monetary quantity.
 */
final class Money
{
    private const OP_SCALE = 6;

    public const SCALE = 4;

    public const ZERO = '0.0000';

    /**
     * Normalise anything that represents an amount into a numeric string.
     * Every ingestion point (request input, model attribute) passes
     * through here once so the rest of the class can trust its inputs.
     *
     * @return numeric-string
     */
    public static function of(int|float|string $value): string
    {
        $string = is_float($value) ? number_format($value, self::SCALE, '.', '') : (string) $value;

        if (! is_numeric($string)) {
            throw new InvalidArgumentException("Not a monetary value: {$string}");
        }

        return $string;
    }

    /**
     * @return numeric-string
     */
    public static function add(int|float|string $a, int|float|string $b): string
    {
        return bcadd(self::of($a), self::of($b), self::OP_SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function sub(int|float|string $a, int|float|string $b): string
    {
        return bcsub(self::of($a), self::of($b), self::OP_SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function mul(int|float|string $a, int|float|string $b): string
    {
        return bcmul(self::of($a), self::of($b), self::OP_SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function div(int|float|string $a, int|float|string $b): string
    {
        $divisor = self::of($b);

        if (self::isZero($divisor)) {
            return self::ZERO;
        }

        return bcdiv(self::of($a), $divisor, self::OP_SCALE);
    }

    /**
     * @param  iterable<int|float|string>  $values
     * @return numeric-string
     */
    public static function sum(iterable $values): string
    {
        $total = self::ZERO;

        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return $total;
    }

    public static function compare(int|float|string $a, int|float|string $b): int
    {
        return bccomp(self::of($a), self::of($b), self::OP_SCALE);
    }

    public static function isZero(int|float|string $value): bool
    {
        return bccomp(self::of($value), '0', self::OP_SCALE) === 0;
    }

    public static function isNegative(int|float|string $value): bool
    {
        return bccomp(self::of($value), '0', self::OP_SCALE) < 0;
    }

    public static function isPositive(int|float|string $value): bool
    {
        return bccomp(self::of($value), '0', self::OP_SCALE) > 0;
    }

    /**
     * Half-up round to the storage scale — the single rounding step §141
     * allows, applied when a computed value is about to be persisted. Works
     * by nudging by half a unit of the last kept place, then letting
     * BCMath's truncation do the rest.
     *
     * @return numeric-string
     */
    public static function round(int|float|string $value, int $scale = self::SCALE): string
    {
        $numeric = self::of($value);
        $bump = self::of('0.'.str_repeat('0', $scale).'5');

        $rounded = self::isNegative($numeric)
            ? bcsub($numeric, $bump, $scale)
            : bcadd($numeric, $bump, $scale);

        return bcadd($rounded, '0', $scale);
    }
}
