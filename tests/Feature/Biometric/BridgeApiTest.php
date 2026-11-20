<?php

namespace Tests\Feature\Biometric;

use App\Models\AttendanceLog;
use App\Models\BiometricPunch;
use Illuminate\Support\Carbon;

/** /api/v1/bridge: authentication, idempotent punches, and what they do to attendance. */
class BridgeApiTest extends BiometricTestCase
{
    // ── authentication ────────────────────────────────────────────────

    public function test_missing_wrong_and_malformed_keys_are_refused(): void
    {
        $this->keyedDevice();

        $this->getJson(route('api.v1.bridge.ping'))->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => 'Bearer not-a-real-key'])->assertUnauthorized();
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => 'Bearer '])->assertUnauthorized();
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => 'Basic abc'])->assertUnauthorized();
        $this->postJson(route('api.v1.bridge.punches'), ['punches' => [$this->punch(1, '2026-11-22 08:00:00')]])->assertUnauthorized();
        $this->assertSame(0, BiometricPunch::count());
    }

    public function test_a_revoked_or_replaced_key_is_refused(): void
    {
        [$device, $key] = $this->keyedDevice();
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"])->assertOk();

        $newKey = $device->generateConnectionKey();
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"])->assertUnauthorized();
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$newKey}"])->assertOk();

        $device->revokeConnectionKey();
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$newKey}"])->assertUnauthorized();
    }

    public function test_an_inactive_device_is_refused(): void
    {
        [$device, $key] = $this->keyedDevice(['is_active' => false]);

        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"])->assertUnauthorized();
        $this->send($key, [$this->punch(1, '2026-11-22 08:00:00')])->assertUnauthorized();

        $device->update(['is_active' => true]);
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"])->assertOk();
    }

    public function test_a_key_belongs_to_its_own_device_only(): void
    {
        [$a, $keyA] = $this->keyedDevice(['name' => 'Entrance']);
        [$b, $keyB] = $this->keyedDevice(['name' => 'Chapel']);
        $this->member(5);
        $this->service();

        $this->send($keyA, [$this->punch(5, '2026-11-22 08:41:05')])->assertOk();

        $this->assertSame(1, $a->punches()->count());
        $this->assertSame(0, $b->punches()->count());
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$keyB}"])->assertOk()->assertJsonPath('device', 'Chapel');
    }

    public function test_a_valid_key_gets_the_ping_and_last_seen_is_updated(): void
    {
        [$device, $key] = $this->keyedDevice();
        $this->assertNull($device->last_seen_at);

        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"])->assertOk()
            ->assertJsonPath('device', 'Main Entrance')
            ->assertJsonPath('server_time', now()->toIso8601String())
            ->assertJsonPath('last_punch_at', null)
            ->assertJsonMissingPath('data');

        $this->assertEquals(now()->timestamp, $device->fresh()->last_seen_at->timestamp);
    }

    public function test_a_logged_in_user_is_not_enough(): void
    {
        $this->keyedDevice();
        $admin = \App\Models\User::factory()->create()->assignRole('Super Admin');

        $this->actingAs($admin)->getJson(route('api.v1.bridge.ping'))->assertUnauthorized();
    }

    public function test_each_key_is_limited_to_60_requests_a_minute(): void
    {
        [, $key] = $this->keyedDevice();
        [, $other] = $this->keyedDevice(['name' => 'Chapel']);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"])->assertOk();
        }

        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"])->assertStatus(429);
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$other}"])->assertOk();
    }

    // ── punches ───────────────────────────────────────────────────────

    public function test_a_punch_from_a_member_creates_a_biometric_attendance_log(): void
    {
        [$device, $key] = $this->keyedDevice();
        $member = $this->member(123);
        $service = $this->service();

        $this->send($key, [$this->punch(123, '2026-11-22 08:41:05')])->assertOk()
            ->assertExactJson(['received' => 1, 'new' => 1, 'duplicates' => 0, 'matched' => 1, 'unmatched' => 0, 'no_service' => 0,
                'duplicate_ignored' => 0, 'kept_manual' => 0, 'last_punch_at' => Carbon::parse('2026-11-22 08:41:05')->toIso8601String()]);

        $log = AttendanceLog::sole();
        $this->assertSame([$service->id, $member->id, 'Present', 'Biometric', 123], [$log->church_service_id, $log->member_id, $log->status, $log->check_in_method, $log->fingerprint_id]);
        $this->assertSame('2026-11-22 08:41:05', $log->check_in_time->format('Y-m-d H:i:s'));

        $punch = BiometricPunch::sole();
        $this->assertSame([$device->id, 123, 'matched', $member->id, $service->id, $log->id], [$punch->biometric_device_id, $punch->device_user_id, $punch->status, $punch->member_id, $punch->service_id, $punch->attendance_log_id]);
        $this->assertNotNull($punch->processed_at);
        $this->assertSame('2026-11-22 08:41:05', $device->fresh()->last_punch_at->format('Y-m-d H:i:s'));
    }

    public function test_sending_the_same_payload_twice_changes_nothing(): void
    {
        [, $key] = $this->keyedDevice();
        $this->member(123);
        $this->service();
        $payload = [$this->punch(123, '2026-11-22 08:41:05'), $this->punch(999, '2026-11-22 08:50:00')];

        $this->send($key, $payload)->assertOk()->assertJsonPath('new', 2);
        $before = [BiometricPunch::count(), AttendanceLog::count(), BiometricPunch::orderBy('id')->pluck('status')->all()];

        $this->send($key, $payload)->assertOk()
            ->assertJsonPath('received', 2)->assertJsonPath('new', 0)->assertJsonPath('duplicates', 2)->assertJsonPath('matched', 0)->assertJsonPath('unmatched', 0);

        $this->assertSame($before, [BiometricPunch::count(), AttendanceLog::count(), BiometricPunch::orderBy('id')->pluck('status')->all()]);
    }

    public function test_the_same_punch_twice_in_one_request_counts_once(): void
    {
        [, $key] = $this->keyedDevice();
        $this->member(123);
        $this->service();

        $this->send($key, [$this->punch(123, '2026-11-22 08:41:05'), $this->punch(123, '2026-11-22 08:41:05')])->assertOk()
            ->assertJsonPath('new', 1)->assertJsonPath('duplicates', 1);
        $this->assertSame(1, BiometricPunch::count());
    }

    public function test_a_late_punch_is_marked_late(): void
    {
        [, $key] = $this->keyedDevice();
        $this->member(1);
        $this->member(2);
        $this->member(3);
        $this->service(start: '09:00:00');

        $this->send($key, [
            $this->punch(1, '2026-11-22 09:30:00'),   // exactly 30 minutes after the start: not late
            $this->punch(2, '2026-11-22 09:30:01'),   // more than 30 minutes: late
            $this->punch(3, '2026-11-22 08:15:00'),   // early
        ])->assertOk();

        $this->assertSame(['Present', 'Late', 'Present'], AttendanceLog::orderBy('member_id')->pluck('status')->all());
    }

    public function test_an_unknown_id_is_stored_as_unmatched_and_nothing_else(): void
    {
        [, $key] = $this->keyedDevice();
        $this->service();

        $this->send($key, [$this->punch(777, '2026-11-22 08:41:05')])->assertOk()->assertJsonPath('unmatched', 1)->assertJsonPath('matched', 0);

        $punch = BiometricPunch::sole();
        $this->assertSame(['unmatched', null, null, null], [$punch->status, $punch->member_id, $punch->service_id, $punch->attendance_log_id]);
        $this->assertSame(0, AttendanceLog::count());
    }

    public function test_a_punch_with_no_service_that_day_is_stored_and_reprocessing_fixes_it(): void
    {
        [, $key] = $this->keyedDevice();
        $member = $this->member(123);

        $this->send($key, [$this->punch(123, '2026-11-22 08:41:05')])->assertOk()->assertJsonPath('no_service', 1)->assertJsonPath('matched', 0);

        $punch = BiometricPunch::sole();
        $this->assertSame(['no_service', $member->id, null], [$punch->status, $punch->member_id, $punch->service_id]);
        $this->assertSame(0, AttendanceLog::count());

        $service = $this->service();   // generated later
        $this->artisan('biometric:reprocess')->assertSuccessful()->expectsOutputToContain('Waiting punches checked: 1')->expectsOutputToContain('now on attendance:     1');

        $this->assertSame(['matched', $service->id], [$punch->fresh()->status, $punch->fresh()->service_id]);
        $this->assertSame(1, AttendanceLog::count());
    }

    public function test_a_manual_mark_is_never_overwritten(): void
    {
        [, $key] = $this->keyedDevice();
        $member = $this->member(123);
        $service = $this->service();
        $marked = AttendanceLog::create(['church_service_id' => $service->id, 'member_id' => $member->id, 'status' => 'Excused',
            'check_in_method' => 'Manual', 'check_in_time' => '2026-11-22 10:00:00', 'notes' => 'Told us in advance']);

        $this->send($key, [$this->punch(123, '2026-11-22 08:41:05')])->assertOk()->assertJsonPath('kept_manual', 1)->assertJsonPath('matched', 0);

        $log = $marked->fresh();
        $this->assertSame(['Excused', 'Manual', '2026-11-22 10:00:00', 'Told us in advance'], [$log->status, $log->check_in_method, $log->check_in_time->format('Y-m-d H:i:s'), $log->notes]);
        $this->assertSame(['kept_manual', $log->id], [BiometricPunch::sole()->status, BiometricPunch::sole()->attendance_log_id]);
    }

    public function test_the_earliest_punch_is_the_check_in_time(): void
    {
        [, $key] = $this->keyedDevice();
        $this->member(123);
        $this->service(start: '09:00:00');

        $this->send($key, [$this->punch(123, '2026-11-22 09:45:00')])->assertOk()->assertJsonPath('matched', 1);
        $this->assertSame('Late', AttendanceLog::sole()->status);

        // A later punch changes nothing
        $this->send($key, [$this->punch(123, '2026-11-22 11:30:00')])->assertOk()->assertJsonPath('duplicate_ignored', 1)->assertJsonPath('matched', 0);
        $this->assertSame('09:45:00', AttendanceLog::sole()->check_in_time->format('H:i:s'));

        // An earlier one (sent afterwards, or from a backlog) replaces it and fixes the status
        $this->send($key, [$this->punch(123, '2026-11-22 08:50:00')])->assertOk()->assertJsonPath('matched', 1);
        $log = AttendanceLog::sole();
        $this->assertSame(['08:50:00', 'Present', 1], [$log->check_in_time->format('H:i:s'), $log->status, AttendanceLog::count()]);
        $this->assertSame(['matched', 'duplicate_ignored', 'matched'], BiometricPunch::orderBy('id')->pluck('status')->all());
    }

    public function test_punches_in_one_request_in_any_order_keep_the_earliest(): void
    {
        [, $key] = $this->keyedDevice();
        $this->member(123);
        $this->service();

        $this->send($key, [$this->punch(123, '2026-11-22 10:15:00'), $this->punch(123, '2026-11-22 08:40:00'), $this->punch(123, '2026-11-22 09:00:00')])->assertOk();

        $this->assertSame('08:40:00', AttendanceLog::sole()->check_in_time->format('H:i:s'));
        $this->assertSame(1, AttendanceLog::count());
    }

    public function test_an_id_shared_by_two_members_is_not_guessed(): void
    {
        [, $key] = $this->keyedDevice();
        $this->member(55, 'First');
        $this->member(55, 'Second');
        $this->service();

        $this->send($key, [$this->punch(55, '2026-11-22 08:41:05')])->assertOk()->assertJsonPath('unmatched', 1);

        $this->assertSame(0, AttendanceLog::count());
    }

    // ── validation ────────────────────────────────────────────────────

    public function test_more_than_500_punches_are_refused(): void
    {
        [, $key] = $this->keyedDevice();
        $punches = array_map(fn ($i) => $this->punch($i, '2026-11-22 08:00:00'), range(1, 501));

        $this->send($key, $punches)->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrorFor('punches');
        $this->assertSame(0, BiometricPunch::count());

        $this->send($key, array_slice($punches, 0, 500))->assertOk()->assertJsonPath('received', 500)->assertJsonPath('new', 500);
    }

    public function test_the_payload_is_validated_strictly_and_nothing_is_saved_on_error(): void
    {
        [, $key] = $this->keyedDevice();
        $good = $this->punch(1, '2026-11-22 08:00:00');

        $bad = [
            'no punches'         => [],
            'user id text'       => [$good, ['user_id' => 'abc', 'timestamp' => '2026-11-22 08:00:00']],
            'negative user id'   => [$good, ['user_id' => -1, 'timestamp' => '2026-11-22 08:00:00']],
            'no timestamp'       => [$good, ['user_id' => 2]],
            'wrong format'       => [$good, ['user_id' => 2, 'timestamp' => '22/11/2026 08:00']],
            'iso format'         => [$good, ['user_id' => 2, 'timestamp' => '2026-11-22T08:00:00Z']],
            'far future'         => [$good, ['user_id' => 2, 'timestamp' => '2026-12-30 08:00:00']],
            'ancient'            => [$good, ['user_id' => 2, 'timestamp' => '1999-01-01 00:00:00']],
            'verify type text'   => [$good, ['user_id' => 2, 'timestamp' => '2026-11-22 08:00:00', 'verify_type' => 'x']],
            'unknown field'      => [$good, ['user_id' => 2, 'timestamp' => '2026-11-22 08:00:00', 'member_id' => 1]],
        ];
        foreach ($bad as $case => $punches) {
            $this->send($key, $punches)->assertStatus(422);
            $this->assertSame(0, BiometricPunch::count(), $case);
        }

        $this->postJson(route('api.v1.bridge.punches'), ['punches' => 'nope'], ['Authorization' => "Bearer {$key}"])->assertStatus(422);
        $this->send($key, [['user_id' => 3, 'timestamp' => '2026-11-22 08:00:00']])->assertOk();   // verify_type is optional
    }
}
