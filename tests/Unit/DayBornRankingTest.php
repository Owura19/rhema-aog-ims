<?php

namespace Tests\Unit;

use App\Support\DayBorn\Ranking;
use App\Support\DayBorn\Weekdays;
use PHPUnit\Framework\TestCase;

/** Day-born ranking: highest first, ties share a position and the next is skipped (1, 1, 3). */
class DayBornRankingTest extends TestCase
{
    public function test_highest_first_with_positions_one_to_seven(): void
    {
        $ranked = Ranking::rank([0 => '10.00', 1 => '70.00', 2 => '30.00', 3 => '40.00', 4 => '20.00', 5 => '60.00', 6 => '50.00']);

        $this->assertSame([1, 5, 6, 3, 2, 4, 0], array_column($ranked, 'weekday'));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_column($ranked, 'position'));
        $this->assertSame('70.00', $ranked[0]['amount']);
    }

    public function test_ties_share_a_position_and_skip_the_next(): void
    {
        $ranked = Ranking::rank([0 => '50', 1 => '20', 2 => '50', 3 => '20', 4 => '5', 5 => '50.00', 6 => '0']);

        $this->assertSame([
            ['position' => 1, 'weekday' => 0, 'amount' => '50.00'],
            ['position' => 1, 'weekday' => 2, 'amount' => '50.00'],   // ties keep weekday order
            ['position' => 1, 'weekday' => 5, 'amount' => '50.00'],
            ['position' => 4, 'weekday' => 1, 'amount' => '20.00'],
            ['position' => 4, 'weekday' => 3, 'amount' => '20.00'],
            ['position' => 6, 'weekday' => 4, 'amount' => '5.00'],
            ['position' => 7, 'weekday' => 6, 'amount' => '0.00'],
        ], $ranked);
    }

    public function test_amounts_are_compared_in_pesewas_not_floats(): void
    {
        $ranked = Ranking::rank([0 => '0.30', 1 => 0.1 + 0.2, 2 => '0.29']);

        $this->assertSame([[1, 0], [1, 1], [3, 2]], array_map(fn ($r) => [$r['position'], $r['weekday']], $ranked));
    }

    public function test_all_seven_groups_have_akan_names(): void
    {
        $this->assertSame([0, 1, 2, 3, 4, 5, 6], Weekdays::numbers());
        $this->assertSame('Sunday (Kwasi/Akosua)', Weekdays::label(0));
        $this->assertSame('Monday (Kwadwo/Adwoa)', Weekdays::label(1));
        $this->assertSame('Tuesday (Kwabena/Abenaa)', Weekdays::label(2));
        $this->assertSame('Wednesday (Kwaku/Akua)', Weekdays::label(3));
        $this->assertSame('Thursday (Yaw/Yaa)', Weekdays::label(4));
        $this->assertSame('Friday (Kofi/Afua)', Weekdays::label(5));
        $this->assertSame('Saturday (Kwame/Amma)', Weekdays::label(6));
    }
}
