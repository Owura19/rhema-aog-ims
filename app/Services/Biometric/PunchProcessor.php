<?php

namespace App\Services\Biometric;

use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\ChurchService;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Turns a stored punch into attendance. Shared by the bridge API and (later) the USB import.
 *
 *  1. Member: the one member whose fingerprint_id equals the device user id. None, or more than
 *     one (the same ID given to two members), means we can't say who it was: unmatched.
 *  2. Service: see serviceFor(). None: no_service (the member is still recorded on the punch).
 *  3. Attendance (one log per member and service, as everywhere else):
 *       - no log yet                     -> Present, or Late when the punch is more than 30 minutes
 *                                           after the service start; check_in_method Biometric.
 *       - a Biometric log                -> the EARLIEST punch is the check-in time. An earlier one
 *                                           replaces it (matched); a later one changes nothing
 *                                           (duplicate_ignored).
 *       - a log marked some other way    -> left exactly as it is (kept_manual): a person's mark
 *                                           is never overwritten by a machine.
 *
 * process() always leaves the punch with a status and processed_at, and is safe to run again.
 */
class PunchProcessor
{
    /** A service counts as "next" when it starts no later than this long after the punch. */
    public const NEXT_SERVICE_WINDOW_MINUTES = 120;

    /** More than this long after the start is Late (the rule BiometricService already used). */
    public const LATE_AFTER_MINUTES = 30;

    public function process(BiometricPunch $punch): BiometricPunch
    {
        return DB::transaction(function () use ($punch) {
            $member = $this->memberFor($punch->device_user_id);
            if (! $member) {
                return $this->finish($punch, BiometricPunch::UNMATCHED);
            }

            $service = $this->serviceFor($punch->punched_at);
            if (! $service) {
                return $this->finish($punch, BiometricPunch::NO_SERVICE, $member);
            }

            [$status, $log] = $this->attend($member, $service, $punch);

            return $this->finish($punch, $status, $member, $service, $log);
        });
    }

    /**
     * Runs the punches still waiting (unmatched, no_service) again, oldest first: for when a
     * member has since been given an ID, or a service has since been created.
     *
     * @return array{checked: int, resolved: int, unmatched: int, no_service: int}
     */
    public function reprocessWaiting(?BiometricDevice $device = null): array
    {
        $result = ['checked' => 0, 'resolved' => 0, 'unmatched' => 0, 'no_service' => 0];

        BiometricPunch::whereIn('status', BiometricPunch::WAITING)
            ->when($device, fn ($q) => $q->where('biometric_device_id', $device->id))
            ->orderBy('punched_at')->orderBy('id')
            ->chunkById(200, function ($punches) use (&$result) {
                foreach ($punches as $punch) {
                    $result['checked']++;
                    $status = $this->process($punch)->status;

                    if (in_array($status, BiometricPunch::WAITING, true)) {
                        $result[$status]++;
                    } else {
                        $result['resolved']++;
                    }
                }
            });

        return $result;
    }

    /**
     * The service a punch belongs to: those on the punch's date that are not cancelled and use
     * biometric check-in.
     *  - none:                 no service
     *  - exactly one:          that one (every punch that day is for it, as the direct sync always did)
     *  - several:              the latest one that started at or before the punch; if none had
     *                          started yet, the earliest one starting within the next 2 hours;
     *                          otherwise none.
     */
    public function serviceFor(Carbon $punchedAt): ?ChurchService
    {
        $services = ChurchService::whereDate('service_date', $punchedAt->toDateString())
            ->where('status', '!=', 'Cancelled')
            ->where('biometric_enabled', true)
            ->orderBy('start_time')->orderBy('id')
            ->get();

        if ($services->count() <= 1) {
            return $services->first();
        }

        $started = $services->filter(fn (ChurchService $s) => $this->startOf($s)->lte($punchedAt));
        if ($started->isNotEmpty()) {
            return $started->last();
        }

        return $services->first(fn (ChurchService $s) => $this->startOf($s)->diffInMinutes($punchedAt, true) <= self::NEXT_SERVICE_WINDOW_MINUTES);
    }

    public function startOf(ChurchService $service): Carbon
    {
        return Carbon::parse($service->service_date->format('Y-m-d') . ' ' . $service->start_time);
    }

    // ── internals ──────────────────────────────────────────────────────

    /** The one member with this device user id; null when none, or when it is ambiguous. */
    private function memberFor(int $deviceUserId): ?Member
    {
        $members = Member::where('fingerprint_id', $deviceUserId)->limit(2)->get();

        return $members->count() === 1 ? $members->first() : null;
    }

    /** @return array{0: string, 1: AttendanceLog} */
    private function attend(Member $member, ChurchService $service, BiometricPunch $punch): array
    {
        $log = AttendanceLog::where('church_service_id', $service->id)->where('member_id', $member->id)->lockForUpdate()->first();
        $status = $this->statusFor($service, $punch->punched_at);

        if (! $log) {
            try {
                $log = AttendanceLog::create([
                    'church_service_id' => $service->id,
                    'member_id'         => $member->id,
                    'status'            => $status,
                    'check_in_method'   => 'Biometric',
                    'check_in_time'     => $punch->punched_at,
                    'fingerprint_id'    => $punch->device_user_id,
                ]);

                return [BiometricPunch::MATCHED, $log];
            } catch (UniqueConstraintViolationException) {
                // Another request created it a moment ago: fall through and treat it as existing.
                $log = AttendanceLog::where('church_service_id', $service->id)->where('member_id', $member->id)->firstOrFail();
            }
        }

        if ($log->check_in_method !== 'Biometric') {
            return [BiometricPunch::KEPT_MANUAL, $log];
        }

        if ($log->check_in_time === null || $punch->punched_at->lt($log->check_in_time)) {
            $log->update(['status' => $status, 'check_in_time' => $punch->punched_at, 'fingerprint_id' => $punch->device_user_id]);

            return [BiometricPunch::MATCHED, $log];
        }

        return [BiometricPunch::DUPLICATE_IGNORED, $log];
    }

    private function statusFor(ChurchService $service, Carbon $punchedAt): string
    {
        return $punchedAt->gt($this->startOf($service)->addMinutes(self::LATE_AFTER_MINUTES)) ? 'Late' : 'Present';
    }

    private function finish(BiometricPunch $punch, string $status, ?Member $member = null, ?ChurchService $service = null, ?AttendanceLog $log = null): BiometricPunch
    {
        $punch->forceFill([
            'status'            => $status,
            'member_id'         => $member?->id,
            'service_id'        => $service?->id,
            'attendance_log_id' => $log?->id,
            'processed_at'      => now(),
        ])->save();

        return $punch;
    }
}
