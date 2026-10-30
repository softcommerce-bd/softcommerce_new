<?php

namespace App\Services\Funnel\Offers;

/**
 * Money as whole minor units (poisha), so sums never drift the way float
 * taka do. The existing tables store prices as DOUBLE(20,2); everything read
 * from them is converted once on the way in and converted back once on the
 * way out, rounding half up to two decimals.
 */
class Money
{
    public static function cents($amount): int
    {
        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }

    /** Taka with exactly the precision the database keeps. */
    public static function amount(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /** Store-formatted text, using the admin's currency and decimal settings. */
    public static function format(int $cents): string
    {
        return format_price(self::amount($cents));
    }

    /** $cents * $percent / 100, rounded half up. */
    public static function percentOf(int $cents, float $percent): int
    {
        return (int) round($cents * $percent / 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Splits $total across weights so the parts add up to exactly $total
     * (largest-remainder method).
     *
     * @param int[] $weights
     * @return int[]
     */
    public static function allocate(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0 || $total <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $parts = [];
        $remainders = [];
        $given = 0;
        foreach ($weights as $i => $weight) {
            $exact = $total * $weight;
            $parts[$i] = intdiv($exact, $sum);
            $remainders[$i] = $exact % $sum;
            $given += $parts[$i];
        }

        arsort($remainders);
        foreach (array_keys($remainders) as $i) {
            if ($given >= $total) {
                break;
            }
            $parts[$i]++;
            $given++;
        }

        ksort($parts);

        return array_values($parts);
    }
}
