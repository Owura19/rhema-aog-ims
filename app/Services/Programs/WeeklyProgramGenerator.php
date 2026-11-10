<?php

namespace App\Services\Programs;

use App\Models\ChurchService;
use App\Models\ProgramServiceLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Turns config/church_programs.php into church_services rows.
 *
 * For each active programme and each matching date from today to today + weeks*7 - 1:
 *  1. (key, date) already in program_service_log → skipped. Covers services staff
 *     have since edited, cancelled or deleted: a handled date is never touched again.
 *  2. A service already exists that date at the same start time (entered by hand,
 *     or under an older key) → logged as 'existing', nothing created.
 *  3. Otherwise the service (Scheduled, no description, created_by NULL) and its log
 *     row are written in one transaction. A unique-index clash from a concurrent
 *     run counts as skipped.
 * It never updates or deletes a service.
 */
final class WeeklyProgramGenerator
{
    /** Must match the church_services.service_type enum. */
    public const SERVICE_TYPES = ['Sunday Service', 'Midweek Service', 'Prayer Meeting', 'Special Event', 'Convention', 'Other'];
    public const DAYS          = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    public const SETTINGS      = ['key', 'name', 'service_type', 'day', 'start_time', 'end_time', 'venue', 'active'];

    public const MIN_WEEKS = 1;
    public const MAX_WEEKS = 26;

    /**
     * Every problem in the config, as readable lines. Empty = valid.
     *
     * @return list<string>
     */
    public function validate(array $programs): array
    {
        $problems = [];
        $seenKeys = [];

        foreach (array_values($programs) as $index => $program) {
            $number = $index + 1;
            $label = "Programme #{$number}" . (is_array($program) && is_string($program['key'] ?? null) ? " ({$program['key']})" : '');

            if (! is_array($program)) {
                $problems[] = "{$label}: must be a list of settings.";
                continue;
            }

            foreach (array_diff(array_keys($program), self::SETTINGS) as $unknown) {
                $problems[] = "{$label}: unknown setting '{$unknown}'.";
            }

            $validator = Validator::make($program, [
                'key'          => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'name'         => ['required', 'string', 'max:255'],
                'service_type' => ['required', 'string', Rule::in(self::SERVICE_TYPES)],
                'day'          => ['required', 'string', Rule::in(self::DAYS)],
                'start_time'   => ['required', 'date_format:H:i'],
                'end_time'     => ['nullable', 'date_format:H:i', 'after:start_time'],
                'venue'        => ['nullable', 'string', 'max:255'],
                'active'       => ['sometimes', 'boolean'],
            ]);

            foreach ($validator->errors()->all() as $message) {
                $problems[] = "{$label}: {$message}";
            }

            if (is_string($program['key'] ?? null)) {
                if (isset($seenKeys[$program['key']])) {
                    $problems[] = "{$label}: duplicate key '{$program['key']}' (already used by programme #{$seenKeys[$program['key']]}).";
                } else {
                    $seenKeys[$program['key']] = $number;
                }
            }
        }

        return $problems;
    }

    /**
     * Expects a validated config. With $dryRun nothing is written.
     *
     * @return array<string, array{active: bool, created: int, existing: int, skipped: int}> by programme key
     */
    public function generate(array $programs, int $weeks, bool $dryRun = false): array
    {
        $first = today();
        $last  = today()->addDays($weeks * 7 - 1);
        $summary = [];

        foreach (array_values($programs) as $program) {
            $counts = ['active' => (bool) ($program['active'] ?? true), 'created' => 0, 'existing' => 0, 'skipped' => 0];

            if ($counts['active']) {
                foreach ($this->dates($program['day'], $first, $last) as $date) {
                    $counts[$this->handle($program, $date, $dryRun)]++;
                }
            }

            $summary[$program['key']] = $counts;
        }

        return $summary;
    }

    /** @return list<Carbon> every $day between $first and $last, inclusive */
    private function dates(string $day, Carbon $first, Carbon $last): array
    {
        $dates = [];

        for ($date = $first->copy(); $date->lte($last); $date->addDay()) {
            if (strtolower($date->englishDayOfWeek) === $day) {
                $dates[] = $date->copy();
            }
        }

        return $dates;
    }

    /** @return 'created'|'existing'|'skipped' */
    private function handle(array $program, Carbon $date, bool $dryRun): string
    {
        $key = $program['key'];
        $day = $date->toDateString();
        $startTime = $program['start_time'] . ':00';

        if (ProgramServiceLog::where('program_key', $key)->where('service_date', $day)->exists()) {
            return 'skipped';
        }

        $existing = ChurchService::where('service_date', $day)->where('start_time', $startTime)->orderBy('id')->first();

        if ($dryRun) {
            return $existing ? 'existing' : 'created';
        }

        try {
            return DB::transaction(function () use ($program, $key, $day, $startTime, $existing) {
                $service = $existing ?? ChurchService::create([
                    'name'         => $program['name'],
                    'service_type' => $program['service_type'],
                    'service_date' => $day,
                    'start_time'   => $startTime,
                    'end_time'     => isset($program['end_time']) ? $program['end_time'] . ':00' : null,
                    'venue'        => $program['venue'] ?? null,
                    'description'  => null,
                    'status'       => 'Scheduled',
                    'created_by'   => null,
                ]);

                ProgramServiceLog::create([
                    'program_key'       => $key,
                    'service_date'      => $day,
                    'church_service_id' => $service->id,
                    'outcome'           => $existing ? ProgramServiceLog::EXISTING : ProgramServiceLog::CREATED,
                ]);

                return $existing ? 'existing' : 'created';
            });
        } catch (UniqueConstraintViolationException) {
            return 'skipped';   // another run handled this date at the same moment; its writes stand, ours rolled back
        }
    }
}
