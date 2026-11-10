<?php

namespace Tests\Feature\Programs;

use App\Models\ChurchService;
use App\Models\ProgramServiceLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * php artisan programs:generate-services. Every test sets its own config.
 * Today is Monday 2026-11-09, so 8 weeks = 2026-11-09 … 2027-01-03.
 */
class ProgramServicesGeneratorTest extends TestCase
{
    use RefreshDatabase;

    /** The 8 Wednesdays from today to today + 55 days. */
    private const WEDNESDAYS = ['2026-11-11', '2026-11-18', '2026-11-25', '2026-12-02', '2026-12-09', '2026-12-16', '2026-12-23', '2026-12-30'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 01:00:00'));
    }

    private function program(array $overrides = []): array
    {
        return $overrides + [
            'key'          => 'midweek-bible-study',
            'name'         => 'Midweek Bible Study',
            'service_type' => 'Midweek Service',
            'day'          => 'wednesday',
            'start_time'   => '18:30',
            'end_time'     => '20:00',
            'venue'        => 'Main Auditorium',
        ];
    }

    private function configure(array ...$programs): void
    {
        config(['church_programs.programs' => $programs]);
    }

    /** @return list<string> */
    private function serviceDates(): array
    {
        return ChurchService::orderBy('service_date')->pluck('service_date')->map->toDateString()->all();
    }

    private function serviceOn(string $date): ?ChurchService
    {
        return ChurchService::where('service_date', $date)->where('start_time', '18:30:00')->first();
    }

    // ── what gets created ──────────────────────────────────────────────

    public function test_an_empty_config_creates_nothing(): void
    {
        $this->configure();

        $this->artisan('programs:generate-services')
            ->expectsOutputToContain('No weekly programmes configured.')
            ->assertSuccessful();

        $this->assertSame(0, ChurchService::count());
        $this->assertSame(0, ProgramServiceLog::count());
    }

    public function test_a_weekly_programme_gets_a_service_for_each_of_the_next_8_weeks(): void
    {
        $this->configure($this->program());

        $this->artisan('programs:generate-services')->assertSuccessful();

        $this->assertSame(self::WEDNESDAYS, $this->serviceDates());

        $service = $this->serviceOn('2026-11-11')->fresh();
        $this->assertSame('Midweek Bible Study', $service->name);
        $this->assertSame('Midweek Service', $service->service_type);
        $this->assertSame('18:30:00', $service->start_time);
        $this->assertSame('20:00:00', $service->end_time);
        $this->assertSame('Main Auditorium', $service->venue);
        $this->assertSame('Scheduled', $service->status);
        $this->assertNull($service->description);
        $this->assertNull($service->created_by);
        $this->assertTrue($service->biometric_enabled);   // the column default, as on the web form

        $this->assertSame(8, ProgramServiceLog::where('program_key', 'midweek-bible-study')->where('outcome', 'created')->count());
        $this->assertSame($service->id, ProgramServiceLog::where('service_date', '2026-11-11')->sole()->church_service_id);
    }

    public function test_today_is_included_and_past_dates_never_are(): void
    {
        $this->configure($this->program(['key' => 'monday-prayer', 'day' => 'monday', 'service_type' => 'Prayer Meeting']));

        $this->artisan('programs:generate-services')->assertSuccessful();

        $dates = $this->serviceDates();
        $this->assertSame('2026-11-09', $dates[0]);
        $this->assertSame('2026-12-28', end($dates));
        $this->assertCount(8, $dates);
    }

    public function test_an_inactive_programme_is_skipped(): void
    {
        $this->configure($this->program(['active' => false]));

        $this->artisan('programs:generate-services')
            ->expectsOutputToContain('midweek-bible-study (inactive)')
            ->assertSuccessful();

        $this->assertSame(0, ChurchService::count());
    }

    public function test_running_again_creates_nothing_new(): void
    {
        $this->configure($this->program());

        $this->artisan('programs:generate-services')->assertSuccessful();
        $this->artisan('programs:generate-services')->assertSuccessful();

        $this->assertSame(8, ChurchService::count());
        $this->assertSame(8, ProgramServiceLog::count());
    }

    // ── staff changes are never undone ─────────────────────────────────

    public function test_a_service_staff_edited_is_not_overwritten_or_duplicated(): void
    {
        $this->configure($this->program());
        $this->artisan('programs:generate-services')->assertSuccessful();

        $edited = $this->serviceOn('2026-11-18');
        $edited->update(['name' => 'Bible Study (guest speaker)', 'start_time' => '19:00', 'venue' => 'Youth Hall']);

        $this->artisan('programs:generate-services')->assertSuccessful();

        $fresh = $edited->fresh();
        $this->assertSame('Bible Study (guest speaker)', $fresh->name);
        $this->assertSame('19:00:00', $fresh->start_time);
        $this->assertSame('Youth Hall', $fresh->venue);
        $this->assertSame(1, ChurchService::where('service_date', '2026-11-18')->count());
    }

    public function test_a_cancelled_service_stays_cancelled(): void
    {
        $this->configure($this->program());
        $this->artisan('programs:generate-services')->assertSuccessful();
        $this->serviceOn('2026-11-25')->update(['status' => 'Cancelled']);

        $this->artisan('programs:generate-services')->assertSuccessful();

        $this->assertSame(['Cancelled'], ChurchService::where('service_date', '2026-11-25')->pluck('status')->all());
    }

    public function test_a_deleted_service_is_not_recreated(): void
    {
        $this->configure($this->program());
        $this->artisan('programs:generate-services')->assertSuccessful();
        $this->serviceOn('2026-12-02')->delete();

        $log = ProgramServiceLog::where('service_date', '2026-12-02')->sole();
        $this->assertNull($log->church_service_id);   // the log row survives, unlinked

        $this->artisan('programs:generate-services')->assertSuccessful();

        $this->assertSame(0, ChurchService::where('service_date', '2026-12-02')->count());
        $this->assertSame(7, ChurchService::count());
    }

    public function test_a_service_already_at_that_date_and_time_is_linked_not_duplicated(): void
    {
        $manual = ChurchService::create([
            'name' => 'Combined Service', 'service_type' => 'Special Event', 'service_date' => '2026-11-18',
            'start_time' => '18:30', 'status' => 'Scheduled',
        ]);
        $this->configure($this->program());

        $this->artisan('programs:generate-services')->assertSuccessful();

        $this->assertSame(8, ChurchService::count());   // 7 created + the manual one
        $this->assertSame(['Combined Service'], ChurchService::where('service_date', '2026-11-18')->pluck('name')->all());
        $log = ProgramServiceLog::where('service_date', '2026-11-18')->sole();
        $this->assertSame('existing', $log->outcome);
        $this->assertSame($manual->id, $log->church_service_id);
    }

    public function test_a_service_at_another_time_that_day_does_not_block_the_programme(): void
    {
        ChurchService::create([
            'name' => 'Morning Prayer', 'service_type' => 'Prayer Meeting', 'service_date' => '2026-11-18',
            'start_time' => '06:00', 'status' => 'Scheduled',
        ]);
        $this->configure($this->program());

        $this->artisan('programs:generate-services')->assertSuccessful();

        $this->assertSame(9, ChurchService::count());
        $this->assertNotNull($this->serviceOn('2026-11-18'));
    }

    // ── bad config, options ────────────────────────────────────────────

    public static function invalidConfigs(): array
    {
        return [
            'bad day'          => [['day' => 'Wed'], 'The selected day is invalid.'],
            'bad service type' => [['service_type' => 'Bible Study'], 'The selected service type is invalid.'],
            'bad start time'   => [['start_time' => '6:30pm'], 'The start time field must match the format H:i.'],
            'end before start' => [['end_time' => '17:00'], 'The end time field must be a date after start time.'],
            'unknown setting'  => [['weekday' => 'wednesday'], "unknown setting 'weekday'"],
            'duplicate key'    => [['key' => 'midweek-bible-study'], "duplicate key 'midweek-bible-study'"],
        ];
    }

    #[DataProvider('invalidConfigs')]
    public function test_any_invalid_entry_stops_everything_and_writes_nothing(array $broken, string $problem): void
    {
        $this->configure(
            $this->program(),                                                                       // valid
            $this->program($broken + ['key' => 'youth-meeting', 'name' => 'Youth Meeting', 'day' => 'friday']),   // the broken one
        );

        $this->artisan('programs:generate-services')
            ->expectsOutputToContain('Nothing was written.')
            ->expectsOutputToContain($problem)
            ->assertFailed();

        $this->assertSame(0, ChurchService::count());
        $this->assertSame(0, ProgramServiceLog::count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        ChurchService::create([
            'name' => 'Combined Service', 'service_type' => 'Special Event', 'service_date' => '2026-11-18',
            'start_time' => '18:30', 'status' => 'Scheduled',
        ]);
        $this->configure($this->program());

        $this->artisan('programs:generate-services', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->expectsTable(['Programme', 'Would create', 'Existing', 'Skipped'], [['midweek-bible-study', 7, 1, 0]])
            ->assertSuccessful();

        $this->assertSame(1, ChurchService::count());
        $this->assertSame(0, ProgramServiceLog::count());
    }

    public static function badWeeks(): array
    {
        return ['zero' => ['0'], 'twenty-seven' => ['27'], 'negative' => ['-1'], 'text' => ['abc']];
    }

    #[DataProvider('badWeeks')]
    public function test_weeks_must_be_1_to_26(string $weeks): void
    {
        $this->configure($this->program());

        $this->artisan('programs:generate-services', ['--weeks' => $weeks])
            ->expectsOutputToContain('--weeks must be a whole number from 1 to 26.')
            ->assertFailed();

        $this->assertSame(0, ChurchService::count());
    }

    public function test_weeks_sets_how_far_ahead(): void
    {
        $this->configure($this->program());

        $this->artisan('programs:generate-services', ['--weeks' => '1'])->assertSuccessful();
        $this->assertSame(['2026-11-11'], $this->serviceDates());

        $this->artisan('programs:generate-services', ['--weeks' => '26'])->assertSuccessful();
        $this->assertSame(26, ChurchService::count());
        $this->assertSame('2027-05-05', last($this->serviceDates()));
    }

    public function test_it_is_scheduled_daily_at_1am_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'programs:generate-services'));

        $this->assertNotNull($event, 'programs:generate-services is not scheduled');
        $this->assertSame('0 1 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
