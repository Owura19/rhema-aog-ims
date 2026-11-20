<?php

namespace Tests\Feature\Biometric;

use App\Models\AttendanceLog;
use App\Models\BiometricPunch;
use App\Services\Biometric\PunchProcessor;
use App\Services\BiometricService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Rats\Zkteco\Lib\ZKTeco;

/** Which service a punch belongs to, reprocessing, and the direct LAN sync that shares the Late rule. */
class PunchProcessorTest extends BiometricTestCase
{
    private function pick(string $time): ?string
    {
        return app(PunchProcessor::class)->serviceFor(Carbon::parse(self::SUNDAY . " {$time}"))?->name;
    }

    // ── choosing the service ──────────────────────────────────────────

    public function test_a_single_service_on_the_day_takes_every_punch_of_that_day(): void
    {
        $this->service(start: '09:00:00');

        foreach (['00:05:00', '06:00:00', '09:00:00', '13:00:00', '23:59:00'] as $time) {
            $this->assertSame('Sunday Service', $this->pick($time), $time);
        }
        $this->assertNull(app(PunchProcessor::class)->serviceFor(Carbon::parse('2026-11-23 09:00:00')));   // another day
    }

    public static function twoServices(): array
    {
        // Morning 09:00 and evening 18:00
        return [
            'long before the first'        => ['06:00:00', null],
            'exactly 2 hours before'       => ['07:00:00', 'Morning'],
            'just over 2 hours before'     => ['06:59:59', null],
            '30 minutes before the first'  => ['08:30:00', 'Morning'],
            'at the first start'           => ['09:00:00', 'Morning'],
            'during the first'             => ['10:30:00', 'Morning'],
            'between them, before 2h'      => ['15:00:00', 'Morning'],   // already started: nearest at or before wins
            'just before the second'       => ['17:59:00', 'Morning'],
            'at the second start'          => ['18:00:00', 'Evening'],
            'after the second'             => ['21:30:00', 'Evening'],
        ];
    }

    #[DataProvider('twoServices')]
    public function test_with_several_services_the_nearest_at_or_before_wins_else_the_next_within_2_hours(string $time, ?string $expected): void
    {
        $this->service(start: '09:00:00', attrs: ['name' => 'Morning']);
        $this->service(start: '18:00:00', attrs: ['name' => 'Evening']);

        $this->assertSame($expected, $this->pick($time));
    }

    public function test_with_only_evening_services_before_the_first_one_uses_the_next_within_2_hours(): void
    {
        $this->service(start: '18:00:00', attrs: ['name' => 'Evening']);
        $this->service(start: '20:00:00', attrs: ['name' => 'Late night']);

        $this->assertSame('Evening', $this->pick('16:30:00'));
        $this->assertNull($this->pick('15:00:00'));
        $this->assertSame('Late night', $this->pick('20:10:00'));
    }

    public function test_cancelled_and_non_biometric_services_are_not_used(): void
    {
        $this->service(start: '09:00:00', attrs: ['name' => 'Cancelled', 'status' => 'Cancelled']);
        $this->service(start: '10:00:00', attrs: ['name' => 'Paper only', 'biometric_enabled' => false]);
        $this->assertNull($this->pick('10:30:00'));

        $this->service(start: '11:00:00', attrs: ['name' => 'Real one']);
        $this->assertSame('Real one', $this->pick('10:30:00'));   // the only usable one that day
    }

    // ── attendance per service ────────────────────────────────────────

    public function test_punches_in_two_services_the_same_day_make_one_log_each(): void
    {
        [, $key] = $this->keyedDevice();
        $this->member(7);
        $morning = $this->service(start: '09:00:00', attrs: ['name' => 'Morning']);
        $evening = $this->service(start: '18:00:00', attrs: ['name' => 'Evening']);

        $this->send($key, [$this->punch(7, '2026-11-22 08:55:00'), $this->punch(7, '2026-11-22 18:05:00')])->assertOk()->assertJsonPath('matched', 2);

        $this->assertEqualsCanonicalizing([$morning->id, $evening->id], AttendanceLog::pluck('church_service_id')->all());
        $this->assertSame(['Present', 'Present'], AttendanceLog::orderBy('church_service_id')->pluck('status')->all());
    }

    // ── reprocessing ──────────────────────────────────────────────────

    public function test_reprocessing_picks_up_a_member_given_an_id_later(): void
    {
        [$device, $key] = $this->keyedDevice();
        $this->service();
        $member = $this->member(null, 'Late', 'Enrolled');

        $this->send($key, [$this->punch(42, '2026-11-22 08:41:05')])->assertOk()->assertJsonPath('unmatched', 1);
        $this->assertSame(0, AttendanceLog::count());

        $member->update(['fingerprint_id' => 42]);
        $result = app(PunchProcessor::class)->reprocessWaiting($device);

        $this->assertSame(['checked' => 1, 'resolved' => 1, 'unmatched' => 0, 'no_service' => 0], $result);
        $this->assertSame(['matched', $member->id], [BiometricPunch::sole()->status, BiometricPunch::sole()->member_id]);
        $this->assertSame(1, AttendanceLog::count());
    }

    public function test_reprocessing_leaves_unresolved_ones_and_everything_else_alone(): void
    {
        [$device, $key] = $this->keyedDevice();
        $this->member(1);
        $this->service();
        $this->send($key, [$this->punch(1, '2026-11-22 08:41:05'), $this->punch(2, '2026-11-22 08:42:00'), $this->punch(1, '2026-11-24 08:00:00')])->assertOk();
        $times = fn () => AttendanceLog::pluck('check_in_time', 'id')->map(fn ($t) => $t->toDateTimeString())->all();
        $logsBefore = $times();

        $result = app(PunchProcessor::class)->reprocessWaiting($device);

        $this->assertSame(['checked' => 2, 'resolved' => 0, 'unmatched' => 1, 'no_service' => 1], $result);
        $this->assertSame($logsBefore, $times());
        $this->assertSame(1, BiometricPunch::where('status', 'matched')->count());
        $this->assertSame(['checked' => 2, 'resolved' => 0, 'unmatched' => 1, 'no_service' => 1], app(PunchProcessor::class)->reprocessWaiting());   // safe to repeat
    }

    public function test_the_reprocess_command_can_be_limited_to_one_device(): void
    {
        [$a, $keyA] = $this->keyedDevice(['name' => 'A']);
        [$b, $keyB] = $this->keyedDevice(['name' => 'B']);
        $this->send($keyA, [$this->punch(1, '2026-11-22 08:00:00')]);
        $this->send($keyB, [$this->punch(1, '2026-11-22 08:00:00')]);
        $this->member(1);
        $this->service();

        $this->artisan('biometric:reprocess', ['--device' => $a->id])->assertSuccessful()->expectsOutputToContain('Waiting punches checked: 1');

        $this->assertSame(['matched', 'unmatched'], [$a->punches()->sole()->status, $b->punches()->sole()->status]);
        $this->artisan('biometric:reprocess', ['--device' => 9999])->assertFailed();
    }

    // ── the direct LAN sync ───────────────────────────────────────────

    public function test_the_lan_sync_always_switches_the_device_back_on(): void
    {
        $device = $this->device(['ip_address' => '192.168.1.50', 'port' => 4370]);
        $service = $this->service();

        foreach ([false, true] as $readFails) {
            $zk = $this->fakeZk($readFails);
            $result = $this->serviceWith($zk)->syncDevice($device, $service);

            $this->assertSame(['connect', 'disable', 'read', 'enable', 'disconnect'], $zk->calls, $readFails ? 'failed read' : 'good read');
            $this->assertSame(! $readFails, $result['success']);
        }
    }

    public function test_the_lan_sync_still_records_attendance(): void
    {
        $device = $this->device(['ip_address' => '192.168.1.50', 'port' => 4370]);
        $service = $this->service();
        $member = $this->member(9);
        $zk = $this->fakeZk(false, [['id' => 9, 'timestamp' => '2026-11-22 09:40:00'], ['id' => 404, 'timestamp' => '2026-11-22 09:41:00'], ['id' => 9, 'timestamp' => '2026-11-21 09:00:00']]);

        $result = $this->serviceWith($zk)->syncDevice($device, $service);

        $this->assertSame([true, 1], [$result['success'], $result['synced']]);
        $log = AttendanceLog::sole();
        $this->assertSame([$member->id, 'Late', 'Biometric'], [$log->member_id, $log->status, $log->check_in_method]);
        $this->assertNotNull($device->fresh()->last_synced_at);
    }

    public function test_a_device_without_an_ip_is_never_dialled(): void
    {
        $device = $this->device();   // bridge device: no IP
        $zk = $this->fakeZk(false);
        $service = $this->serviceWith($zk);

        $this->assertFalse($service->syncDevice($device, $this->service())['success']);
        $this->assertSame(['success' => false, 'message' => BiometricService::NO_IP_MESSAGE], $service->testConnection($device));
        $this->assertSame([], $zk->calls);
    }

    /** A pretend ZKTeco that records the calls it receives. @param array<int, array{id:int, timestamp:string}> $records */
    private function fakeZk(bool $readFails, array $records = []): ZKTeco
    {
        return new class('127.0.0.1', 4370, $readFails, $records) extends ZKTeco {
            public array $calls = [];

            public function __construct($ip, $port, private bool $readFails, private array $records)
            {
                // Deliberately NOT calling parent::__construct(): it opens a UDP socket (the sockets
                // extension isn't enabled everywhere) and this fake never talks to anything.
            }

            public function connect() { $this->calls[] = 'connect'; return true; }
            public function disconnect() { $this->calls[] = 'disconnect'; return true; }
            public function disableDevice() { $this->calls[] = 'disable'; return true; }
            public function enableDevice() { $this->calls[] = 'enable'; return true; }

            public function getAttendance()
            {
                $this->calls[] = 'read';
                if ($this->readFails) {
                    throw new \RuntimeException('read failed');
                }

                return $this->records;
            }
        };
    }

    private function serviceWith(ZKTeco $zk): BiometricService
    {
        return new class($zk) extends BiometricService {
            public function __construct(private ZKTeco $zk) {}

            protected function connect(\App\Models\BiometricDevice $device): ?ZKTeco
            {
                $this->zk->connect();

                return $this->zk;
            }
        };
    }
}
