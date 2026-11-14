<?php

namespace App\Support\DayBorn;

use App\Support\Api\Money;

/**
 * Ranks the 7 group totals, highest first. Equal amounts share a position and the next
 * position is skipped ("1, 1, 3"). Within a tie, the groups keep weekday order.
 * Amounts are compared in whole pesewas, never as floats.
 */
final class Ranking
{
    /**
     * @param  array<int, string|float|int>  $amounts  weekday => amount
     * @return list<array{position: int, weekday: int, amount: string}>
     */
    public static function rank(array $amounts): array
    {
        $rows = [];
        foreach ($amounts as $weekday => $amount) {
            $rows[] = ['weekday' => (int) $weekday, 'cents' => Money::toCents($amount)];
        }

        usort($rows, fn (array $a, array $b) => [$b['cents'], $a['weekday']] <=> [$a['cents'], $b['weekday']]);

        $ranked = [];
        $position = 0;
        $previous = null;
        foreach ($rows as $index => $row) {
            if ($row['cents'] !== $previous) {
                $position = $index + 1;
                $previous = $row['cents'];
            }
            $ranked[] = ['position' => $position, 'weekday' => $row['weekday'], 'amount' => Money::fromCents($row['cents'])];
        }

        return $ranked;
    }
}
