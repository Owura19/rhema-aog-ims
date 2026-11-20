<?php

namespace Tests\Feature\Biometric;

use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\User;

/** The device pages, and the connection key (shown once, stored only as a hash). */
class DevicePagesTest extends BiometricTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create()->assignRole('Super Admin');
    }

    // ── add / edit ────────────────────────────────────────────────────

    public function test_a_bridge_device_needs_no_ip_or_port(): void
    {
        $this->actingAs($this->admin)->get(route('devices.create'))->assertOk()
            ->assertSee('Connects through the church bridge computer; IP not needed.')
            ->assertSee('name="serial_number"', false);

        $this->actingAs($this->admin)->post(route('devices.store'), ['name' => 'Main Entrance', 'serial_number' => 'CKPH123', 'location' => 'Hall', 'is_active' => '1', 'port' => '4370'])
            ->assertRedirect(route('devices.index'))->assertSessionHasNoErrors();

        $device = BiometricDevice::sole();
        $this->assertSame(['CKPH123', null, null, true, false], [$device->serial_number, $device->ip_address, $device->port, $device->is_active, $device->has_ip]);
    }

    public function test_a_device_with_an_ip_needs_a_valid_ip_and_a_port(): void
    {
        $post = fn (array $d) => $this->actingAs($this->admin)->post(route('devices.store'), $d + ['name' => 'LAN device']);

        $post(['ip_address' => '192.168.1.100'])->assertSessionHasErrors('port');
        $post(['ip_address' => 'not-an-ip', 'port' => '4370'])->assertSessionHasErrors('ip_address');
        $post(['ip_address' => '192.168.1.100', 'port' => '99999'])->assertSessionHasErrors('port');
        $this->assertSame(0, BiometricDevice::count());

        $post(['ip_address' => '192.168.1.100', 'port' => '4370'])->assertSessionHasNoErrors();
        $this->assertTrue(BiometricDevice::sole()->has_ip);
    }

    public function test_the_serial_number_is_unique_but_may_stay_on_its_own_device(): void
    {
        $a = $this->device(['name' => 'A', 'serial_number' => 'SN-1']);
        $b = $this->device(['name' => 'B', 'serial_number' => 'SN-2']);

        $this->actingAs($this->admin)->post(route('devices.store'), ['name' => 'C', 'serial_number' => 'SN-1'])->assertSessionHasErrors('serial_number');
        $this->actingAs($this->admin)->put(route('devices.update', $b), ['name' => 'B', 'serial_number' => 'SN-1'])->assertSessionHasErrors('serial_number');
        $this->actingAs($this->admin)->put(route('devices.update', $a), ['name' => 'A renamed', 'serial_number' => 'SN-1'])->assertSessionHasNoErrors();

        $this->assertSame('A renamed', $a->fresh()->name);
        $this->actingAs($this->admin)->get(route('devices.edit', $a))->assertOk()->assertSee('value="SN-1"', false);
    }

    public function test_editing_cannot_change_the_key_or_the_times(): void
    {
        [$device] = $this->keyedDevice();
        $hash = $device->fresh()->api_token_hash;

        $this->actingAs($this->admin)->put(route('devices.update', $device), [
            'name' => 'Renamed', 'api_token_hash' => str_repeat('a', 64), 'last_seen_at' => '2020-01-01 00:00:00', 'last_punch_at' => '2020-01-01 00:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame([$hash, null, null], [$device->fresh()->api_token_hash, $device->fresh()->last_seen_at, $device->fresh()->last_punch_at]);
    }

    // ── list and direct-connection actions ────────────────────────────

    public function test_the_list_offers_test_connection_only_for_devices_with_an_ip(): void
    {
        $bridge = $this->device(['name' => 'Bridge device']);
        $lan = $this->device(['name' => 'LAN device', 'ip_address' => '192.168.1.50', 'port' => 4370]);

        $html = $this->actingAs($this->admin)->get(route('devices.index'))->assertOk()
            ->assertSee('Church bridge computer')->assertSee('192.168.1.50:4370')->getContent();

        $this->assertStringContainsString(route('devices.test', $lan), $html);
        $this->assertStringNotContainsString(route('devices.test', $bridge), $html);
    }

    public function test_test_connection_on_a_bridge_device_explains_instead_of_failing(): void
    {
        $bridge = $this->device();

        $this->actingAs($this->admin)->post(route('devices.test', $bridge))->assertRedirect()
            ->assertSessionHas('error', \App\Services\BiometricService::NO_IP_MESSAGE);
    }

    public function test_the_sync_from_device_list_on_a_service_leaves_out_devices_without_an_ip(): void
    {
        $service = $this->service();
        $this->device(['name' => 'Bridge device']);

        $this->actingAs($this->admin)->get(route('services.show', $service))->assertOk()
            ->assertDontSee('Sync from Device')->assertSee('No device can be read directly');

        $this->device(['name' => 'LAN device', 'ip_address' => '192.168.1.50', 'port' => 4370]);
        $this->actingAs($this->admin)->get(route('services.show', $service))->assertOk()
            ->assertSee('Sync from Device')->assertSee('LAN device (192.168.1.50)')->assertDontSee('Bridge device (');
    }

    // ── the connection key ────────────────────────────────────────────

    public function test_the_key_is_shown_once_and_only_its_hash_is_stored(): void
    {
        $device = $this->device();

        $response = $this->actingAs($this->admin)->post(route('devices.key.generate', $device))->assertOk()
            ->assertSee('Copy this key now. It cannot be shown again.')->assertSee('id="copy-key"', false);
        $key = $response->viewData('newKey');

        $this->assertGreaterThanOrEqual(40, strlen($key));
        $response->assertSee($key, false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $row = (array) \DB::table('biometric_devices')->where('id', $device->id)->first();
        $this->assertSame(hash('sha256', $key), $row['api_token_hash']);
        $this->assertSame(64, strlen($row['api_token_hash']));
        $this->assertNotNull($row['token_created_at']);
        $this->assertStringNotContainsString($key, json_encode($row), 'the key is not stored anywhere in the device row');
        $this->assertStringNotContainsString($key, json_encode(session()->all()), 'not in the session either');

        // Gone for good: the device page no longer has it
        $this->actingAs($this->admin)->get(route('devices.show', $device))->assertOk()->assertDontSee($key, false)->assertDontSee('Copy this key now')
            ->assertSee('Regenerate key')->assertSee('created 22 Nov 2026');
        $this->assertStringNotContainsString($key, json_encode($device->fresh()->toArray()));
    }

    public function test_regenerating_replaces_the_key_at_once_and_revoking_clears_it(): void
    {
        [$device, $old] = $this->keyedDevice();
        $auth = fn (string $key) => $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"]);
        $auth($old)->assertOk();

        $response = $this->actingAs($this->admin)->post(route('devices.key.generate', $device))->assertOk()->assertSee('The previous key has stopped working.');
        $new = $response->viewData('newKey');

        $this->assertNotSame($old, $new);
        $auth($old)->assertUnauthorized();
        $auth($new)->assertOk();

        $this->actingAs($this->admin)->delete(route('devices.key.revoke', $device))->assertRedirect(route('devices.show', $device));
        $this->assertSame([null, null], [$device->fresh()->api_token_hash, $device->fresh()->token_created_at]);
        $auth($new)->assertUnauthorized();
    }

    public function test_every_key_is_different(): void
    {
        $keys = [];
        foreach (range(1, 20) as $i) {
            $keys[] = $this->device(['name' => "D{$i}"])->generateConnectionKey();
        }

        $this->assertCount(20, array_unique($keys));
        $this->assertSame(20, BiometricDevice::whereNotNull('api_token_hash')->distinct()->count('api_token_hash'));
    }

    // ── the device page ───────────────────────────────────────────────

    public function test_the_page_renders_for_a_new_device_with_nothing_yet(): void
    {
        $device = $this->device(['name' => 'Fresh Device', 'serial_number' => 'SN-NEW', 'location' => 'Chapel']);

        $this->actingAs($this->admin)->get(route('devices.show', $device))->assertOk()
            ->assertSee('Fresh Device')->assertSee('SN-NEW')->assertSee('Chapel')
            ->assertSee('Not set')->assertSee('Generate connection key')->assertSee('Never')->assertSee('None yet')
            ->assertSee('No punches received yet.')->assertSee('Every punch so far belongs to a member.')
            ->assertSee(url('/api/v1/bridge'));
    }

    public function test_the_page_shows_counts_recent_punches_and_unmatched_ids(): void
    {
        [$device, $key] = $this->keyedDevice(['name' => 'Busy Device']);
        $this->member(1, 'Ama', 'Mensah');
        $this->service();
        $this->send($key, [
            $this->punch(1, '2026-11-22 08:41:05'),
            $this->punch(777, '2026-11-22 08:50:00'), $this->punch(777, '2026-11-22 09:10:00'), $this->punch(888, '2026-11-22 09:20:00'),
            $this->punch(1, '2026-11-24 08:00:00'),   // a day with no service
        ])->assertOk();

        $response = $this->actingAs($this->admin)->get(route('devices.show', $device))->assertOk();

        $response->assertSee('Ama Mensah')->assertSee('Unmatched ID 777')->assertSee('Unmatched ID 888')
            ->assertSee('Unmatched IDs (2)')->assertSee('Reprocess waiting punches (4)')
            ->assertSee('On attendance')->assertSee('No service that day')->assertSee('24 Nov 2026');
        $this->assertSame([1, 3, 1, 5], [$response->viewData('counts')['matched'], $response->viewData('counts')['unmatched'], $response->viewData('counts')['no_service'], $response->viewData('total')]);
        $ids = $response->viewData('unmatchedIds');
        $this->assertSame([[777, 2], [888, 1]], $ids->map(fn ($r) => [(int) $r->device_user_id, (int) $r->punches])->all());
    }

    public function test_the_page_lists_only_the_last_20_punches(): void
    {
        [$device, $key] = $this->keyedDevice();
        $this->send($key, array_map(fn ($i) => $this->punch(1000 + $i, sprintf('2026-11-22 08:%02d:00', $i)), range(1, 30)))->assertOk();

        $response = $this->actingAs($this->admin)->get(route('devices.show', $device))->assertOk();

        $this->assertSame(range(1030, 1011), $response->viewData('recent')->pluck('device_user_id')->all());   // newest first, the oldest 10 left out
        $response->assertSee('Unmatched ID 1030')->assertSee('Unmatched ID 1011')->assertDontSee('Unmatched ID 1010');
    }

    public function test_the_reprocess_button_runs_waiting_punches(): void
    {
        [$device, $key] = $this->keyedDevice();
        $this->send($key, [$this->punch(5, '2026-11-22 08:00:00')])->assertOk();
        $this->member(5);
        $this->service();

        $this->actingAs($this->admin)->post(route('devices.reprocess', $device))->assertRedirect(route('devices.show', $device))
            ->assertSessionHas('success', '1 waiting punches checked: 1 now on attendance, 0 still unmatched, 0 still without a service.');

        $this->assertSame(1, AttendanceLog::count());
        $this->assertSame(0, BiometricPunch::whereIn('status', BiometricPunch::WAITING)->count());
        $this->actingAs($this->admin)->post(route('devices.reprocess', $device))->assertSessionHas('success', 'No waiting punches.');
    }

    public function test_only_super_admins_can_use_the_device_pages(): void
    {
        $device = $this->device();
        $finance = User::factory()->create()->assignRole('Finance Officer');

        $this->actingAs($finance)->get(route('devices.show', $device))->assertForbidden();
        $this->actingAs($finance)->post(route('devices.key.generate', $device))->assertForbidden();
        $this->actingAs($finance)->delete(route('devices.key.revoke', $device))->assertForbidden();
        $this->actingAs($finance)->post(route('devices.reprocess', $device))->assertForbidden();
        $this->assertNull($device->fresh()->api_token_hash);
    }

    public function test_removing_a_device_removes_its_punches_but_not_the_attendance(): void
    {
        [$device, $key] = $this->keyedDevice();
        $this->member(1);
        $this->service();
        $this->send($key, [$this->punch(1, '2026-11-22 08:00:00')])->assertOk();

        $this->actingAs($this->admin)->delete(route('devices.destroy', $device))->assertRedirect();

        $this->assertSame([0, 1], [BiometricPunch::count(), AttendanceLog::count()]);
        $this->getJson(route('api.v1.bridge.ping'), ['Authorization' => "Bearer {$key}"])->assertUnauthorized();
    }
}
