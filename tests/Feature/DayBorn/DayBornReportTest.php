<?php

namespace Tests\Feature\DayBorn;

use App\Models\DayBornOffering;
use App\Models\DayBornWeek;
use App\Models\User;
use App\Support\DayBorn\Report;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** /day-born/report and /day-born/export: published weeks only. Today is 2026-11-16. */
class DayBornReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @param array<int, string> $amounts weekday => amount (others 0) */
    private function week(string $date, array $amounts, string $status = DayBornWeek::STATUS_PUBLISHED, bool $special = false): DayBornWeek
    {
        $week = DayBornWeek::create(['service_date' => $date, 'status' => $status, 'special_service' => $special]);
        foreach (range(0, 6) as $weekday) {
            DayBornOffering::create(['week_id' => $week->id, 'weekday' => $weekday, 'amount' => $amounts[$weekday] ?? '0']);
        }

        return $week;
    }

    private function seedWeeks(): void
    {
        $this->week('2026-10-04', [5 => '100', 0 => '40']);             // Friday wins
        $this->week('2026-10-11', [5 => '60', 0 => '60', 1 => '10']);   // Friday and Sunday tie for 1st
        $this->week('2026-11-01', [0 => '90.50', 6 => '5']);            // Sunday wins
        $this->week('2026-11-08', [2 => '999']);                        // a draft: ignored
        DayBornWeek::where('service_date', '2026-11-08')->update(['status' => DayBornWeek::STATUS_DRAFT]);
        $this->week('2025-12-28', [3 => '500']);                        // another year
    }

    public function test_year_totals_and_weeks_won_from_published_weeks(): void
    {
        $this->seedWeeks();

        $report = new Report(2026);

        $this->assertCount(3, $report->weeks);
        $this->assertSame('365.50', $report->total);
        $this->assertSame([
            0 => ['weekday' => 0, 'position' => 1, 'total' => '190.50', 'weeks_won' => 2],
            5 => ['weekday' => 5, 'position' => 2, 'total' => '160.00', 'weeks_won' => 2],
            1 => ['weekday' => 1, 'position' => 3, 'total' => '10.00', 'weeks_won' => 0],
            6 => ['weekday' => 6, 'position' => 4, 'total' => '5.00', 'weeks_won' => 0],
        ], array_slice($report->groups, 0, 4, true));
        $this->assertSame([2026, 2025], Report::years(2026));
    }

    public function test_a_month(): void
    {
        $this->seedWeeks();

        $report = new Report(2026, 11);

        $this->assertCount(1, $report->weeks);
        $this->assertSame('95.50', $report->total);
        $this->assertSame(1, $report->groups[0]['weeks_won']);
        $this->assertSame(0, $report->groups[5]['weeks_won']);
    }

    public function test_the_report_page_and_access(): void
    {
        $this->seedWeeks();
        $pastor = User::factory()->create()->assignRole('Pastor');
        $hod = User::factory()->create()->assignRole('HOD');

        $this->actingAs($pastor)->get(route('day-born.report', ['year' => 2026]))->assertOk()
            ->assertSeeInOrder(['1st', 'Sunday', 'Kwasi/Akosua', '190.50', '2nd', 'Friday', '160.00'])
            ->assertSee('Total (3 weeks)')
            ->assertSee('365.50')
            ->assertDontSee('999.00');

        $this->actingAs($pastor)->get(route('day-born.report', ['month' => 13]))->assertSessionHasErrors('month');
        $this->actingAs($hod)->get(route('day-born.report'))->assertForbidden();
        $this->actingAs($hod)->get(route('day-born.export'))->assertForbidden();
    }

    public function test_the_excel_export(): void
    {
        $this->seedWeeks();
        $usher = User::factory()->create()->assignRole('Usher');

        $response = $this->actingAs($usher)->get(route('day-born.export', ['year' => 2026]));
        $response->assertOk()->assertDownload('day-born-offering-2026.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'dbx');
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        unlink($path);

        $weeks = $book->getSheetByName('Weeks');
        $this->assertSame('Service date', $weeks->getCell('A1')->getValue());
        $this->assertSame('Friday (Kofi/Afua)', $weeks->getCell('H1')->getValue());
        $this->assertSame('2026-10-04', $weeks->getCell('A2')->getValue());
        $this->assertEquals(100, $weeks->getCell('H2')->getValue());
        $this->assertEquals(140, $weeks->getCell('J2')->getValue());
        $this->assertSame('Sunday, Friday', $weeks->getCell('K3')->getValue());
        $this->assertNull($weeks->getCell('A5')->getValue());   // 3 published weeks of 2026 only

        $summary = $book->getSheetByName('Summary');
        $this->assertSame('Sunday (Kwasi/Akosua)', $summary->getCell('B2')->getValue());
        $this->assertEquals(190.5, $summary->getCell('C2')->getValue());
        $this->assertEquals(2, $summary->getCell('D2')->getValue());
        $this->assertEquals(365.5, $summary->getCell('C9')->getValue());
    }
}
