<?php

namespace Tests\Feature\Biometric;

use App\Models\BiometricDevice;
use App\Models\ChurchService;
use App\Models\Member;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Shared fixtures. Today is Sunday 2026-11-22, 12:00. Abstract: PHPUnit runs the subclasses. */
abstract class BiometricTestCase extends TestCase
{
    use RefreshDatabase;

    protected const SUNDAY = '2026-11-22';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-22 12:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function device(array $attrs = []): BiometricDevice
    {
        return BiometricDevice::create($attrs + ['name' => 'Main Entrance', 'is_active' => true]);
    }

    /** A device that already has a connection key; returns [device, plain key]. */
    protected function keyedDevice(array $attrs = []): array
    {
        $device = $this->device($attrs);

        return [$device, $device->generateConnectionKey()];
    }

    protected function member(?int $fingerprintId, string $first = 'Test', string $last = 'Member'): Member
    {
        return Member::create(['first_name' => $first, 'last_name' => $last, 'fingerprint_id' => $fingerprintId]);
    }

    protected function service(string $date = self::SUNDAY, string $start = '09:00:00', array $attrs = []): ChurchService
    {
        return ChurchService::create($attrs + [
            'name' => 'Sunday Service', 'service_type' => 'Sunday Service', 'service_date' => $date,
            'start_time' => $start, 'status' => 'Scheduled', 'biometric_enabled' => true,
        ]);
    }

    /** One punch as the bridge sends it. */
    protected function punch(int $userId, string $timestamp, ?int $verifyType = 1): array
    {
        return ['user_id' => $userId, 'timestamp' => $timestamp, 'verify_type' => $verifyType];
    }

    protected function send(string $key, array $punches): TestResponse
    {
        return $this->postJson(route('api.v1.bridge.punches'), ['punches' => $punches], ['Authorization' => 'Bearer ' . $key]);
    }
}
