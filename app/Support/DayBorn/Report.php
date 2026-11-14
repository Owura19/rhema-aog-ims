<?php

namespace App\Support\DayBorn;

use App\Models\DayBornWeek;
use App\Support\Api\Money;
use Illuminate\Support\Collection;

/**
 * Totals over a year (or one month of it), from PUBLISHED weeks only: each group's total,
 * how many weeks it came 1st (a shared 1st counts for every group in the tie), and the
 * weeks themselves. Sums are done in pesewas.
 */
final class Report
{
    /** @var Collection<int, DayBornWeek> */
    public readonly Collection $weeks;

    /** @var array<int, array{weekday: int, total: string, weeks_won: int}> weekday => row, highest total first */
    public readonly array $groups;

    public readonly string $total;

    public function __construct(public readonly int $year, public readonly ?int $month = null)
    {
        $this->weeks = DayBornWeek::published()
            ->with('offerings')
            ->whereYear('service_date', $year)
            ->when($month, fn ($q) => $q->whereMonth('service_date', $month))
            ->orderBy('service_date')
            ->get();

        $cents = array_fill_keys(Weekdays::numbers(), 0);
        $won = array_fill_keys(Weekdays::numbers(), 0);
        foreach ($this->weeks as $week) {
            foreach ($week->amounts() as $weekday => $amount) {
                $cents[$weekday] += Money::toCents($amount);
            }
            if (Money::toCents($week->total()) > 0) {
                foreach ($week->ranking() as $row) {
                    if ($row['position'] === 1) {
                        $won[$row['weekday']]++;
                    }
                }
            }
        }

        $groups = [];
        foreach (Ranking::rank(array_map(fn ($c) => Money::fromCents($c), $cents)) as $row) {
            $groups[$row['weekday']] = ['weekday' => $row['weekday'], 'position' => $row['position'], 'total' => $row['amount'], 'weeks_won' => $won[$row['weekday']]];
        }
        $this->groups = $groups;
        $this->total = Money::fromCents(array_sum($cents));
    }

    /** @return list<int> years that have published weeks, newest first (always includes $current) */
    public static function years(int $current): array
    {
        $years = DayBornWeek::published()->selectRaw('DISTINCT YEAR(service_date) AS y')->pluck('y')->map(fn ($y) => (int) $y)->all();
        $years[] = $current;
        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
    }
}
