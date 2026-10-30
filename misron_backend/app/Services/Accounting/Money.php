<?php

namespace App\Services\Accounting;

use InvalidArgumentException;

/**
 * Exact money arithmetic on integers. Amounts are carried as "micro" units (1/10000) so unit costs with four decimals,
 * quantities and sums never touch floating point; journals round once to two decimals at the very end.
 */
final class Money
{
    public const SCALE = 10000;

    /** "12.3456" | 12.5 | 12 -> 123456 (rejects more than 4 decimals instead of silently rounding a cost) */
    public static function micro($value): int
    {
        $s = trim((string) $value);
        if (!preg_match('/^-?\d+(\.\d{1,4})?$/', $s)) {
            // floats such as 0.1+0.2 arrive as 0.30000000000000004: accept anything within 1e-9 of 4 decimals
            if (is_numeric($s) && abs(((float) $s * self::SCALE) - round((float) $s * self::SCALE)) < 1e-4) {
                return (int) round((float) $s * self::SCALE);
            }
            throw new InvalidArgumentException("Not a valid amount with at most 4 decimals: '{$value}'.");
        }
        $neg = $s[0] === '-';
        [$whole, $frac] = array_pad(explode('.', ltrim($s, '-')), 2, '');
        $v = (int) $whole * self::SCALE + (int) str_pad($frac, 4, '0');

        return $neg ? -$v : $v;
    }

    /** micro -> cents, half away from zero */
    public static function cents(int $micro): int
    {
        return (int) (intdiv(abs($micro) + 50, 100) * ($micro < 0 ? -1 : 1));
    }

    public static function decimal(int $micro, int $places = 4): string
    {
        $neg = $micro < 0;
        $abs = abs($micro);
        $s = sprintf('%d.%04d', intdiv($abs, self::SCALE), $abs % self::SCALE);
        if ($places === 2) {
            $c = abs(self::cents($micro));
            $s = sprintf('%d.%02d', intdiv($c, 100), $c % 100);
        }

        return ($neg && $s !== '0.0000' && $s !== '0.00' ? '-' : '') . $s;
    }

    /**
     * Splits $total across weights so the parts sum to exactly $total (largest remainder; ties go to the earliest line).
     *
     * @param int[] $weights
     * @return int[]
     */
    public static function allocate(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        $out = array_fill_keys(array_keys($weights), 0);
        if ($sum <= 0 || $total === 0) {
            return $out;
        }
        $rem = [];
        $given = 0;
        foreach ($weights as $k => $w) {
            $exact = $total * $w;
            $out[$k] = intdiv($exact, $sum);
            $rem[$k] = $exact % $sum;
            $given += $out[$k];
        }
        arsort($rem);
        foreach (array_keys($rem) as $k) {
            if ($given >= $total) {
                break;
            }
            $out[$k]++;
            $given++;
        }

        return $out;
    }
}
