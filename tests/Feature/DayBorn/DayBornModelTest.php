<?php

namespace Tests\Feature\DayBorn;

use App\Models\DayBornOffering;
use App\Models\DayBornWeek;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The day-born tables and the week model (amounts, total, ranking). */
class DayBornModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_week_is_a_draft_with_seven_groups_totalled_exactly(): void
    {
        $week = DayBornWeek::create(['service_date' => '2026-11-15']);
        foreach ([0 => '10.10', 1 => '0.20', 5 => '99.70'] as $weekday => $amount) {
            DayBornOffering::create(['week_id' => $week->id, 'weekday' => $weekday, 'amount' => $amount]);
        }
        $week = $week->fresh()->load('offerings');

        $this->assertSame(DayBornWeek::STATUS_DRAFT, $week->status);
        $this->assertFalse($week->special_service);
        $this->assertSame([0 => '10.10', 1 => '0.20', 2 => '0.00', 3 => '0.00', 4 => '0.00', 5 => '99.70', 6 => '0.00'], $week->amounts());
        $this->assertSame('110.00', $week->total());
        $this->assertSame([5, 0, 1], array_slice(array_column($week->ranking(), 'weekday'), 0, 3));
        $this->assertSame('2026-11-15', $week->getRouteKey());
    }

    public function test_one_week_per_date_and_one_row_per_group(): void
    {
        $week = DayBornWeek::create(['service_date' => '2026-11-15']);
        DayBornOffering::create(['week_id' => $week->id, 'weekday' => 0, 'amount' => 1]);

        try {
            DayBornOffering::create(['week_id' => $week->id, 'weekday' => 0, 'amount' => 2]);
            $this->fail('Two rows for one group');
        } catch (QueryException) {
            // expected
        }

        $this->expectException(QueryException::class);
        DayBornWeek::create(['service_date' => '2026-11-15']);
    }
}
