<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\ChurchService;
use App\Models\Member;
use Rats\Zkteco\Lib\ZKTeco;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Direct LAN access to a ZKTeco device (the server dials the device's IP and port). Devices that
 * are only reachable through the church bridge computer have no IP and are never dialled: their
 * punches arrive on /api/v1/bridge and are handled by App\Services\Biometric\PunchProcessor.
 */
class BiometricService
{
    public const NO_IP_MESSAGE = 'This device has no IP address: it connects through the church bridge computer, so the server cannot dial it.';

    public function syncDevice(BiometricDevice $device, ChurchService $service): array
    {
        $results = [
            'success'   => false,
            'synced'    => 0,
            'errors'    => 0,
            'message'   => '',
            'logs'      => [],
        ];

        if (! $device->has_ip) {
            $results['message'] = self::NO_IP_MESSAGE;
            return $results;
        }

        try {
            $zk = $this->connect($device);

            if (! $zk) {
                $results['message'] = "Could not connect to device {$device->name} at {$device->ip_address}:{$device->port}";
                return $results;
            }

            // Pause the device while reading; it is ALWAYS switched back on afterwards, even if
            // the read fails, so a failed sync never leaves the device disabled.
            try {
                $zk->disableDevice();
                $attendance = $zk->getAttendance();
            } finally {
                $this->release($zk);
            }

            if (empty($attendance)) {
                $results['success'] = true;
                $results['message'] = 'Device connected but no attendance records found.';
                return $results;
            }

            // Filter records for today's service date
            $serviceDate = $service->service_date->format('Y-m-d');

            foreach ($attendance as $record) {
                try {
                    $punchTime = Carbon::parse($record['timestamp']);

                    // Only process records for this service date
                    if ($punchTime->format('Y-m-d') !== $serviceDate) {
                        continue;
                    }

                    $fingerprintId = (int) $record['id'];

                    // Find member by fingerprint ID
                    $member = Member::where('fingerprint_id', $fingerprintId)->first();

                    if (!$member) {
                        $results['logs'][] = "No member found for fingerprint ID: {$fingerprintId}";
                        continue;
                    }

                    // Determine if late (30 mins after service start)
                    $serviceStart = Carbon::parse($service->service_date->format('Y-m-d') . ' ' . $service->start_time);
                    $isLate = $punchTime->gt($serviceStart->addMinutes(30));

                    // Create or update attendance log
                    AttendanceLog::updateOrCreate(
                        [
                            'church_service_id' => $service->id,
                            'member_id'         => $member->id,
                        ],
                        [
                            'status'           => $isLate ? 'Late' : 'Present',
                            'check_in_method'  => 'Biometric',
                            'check_in_time'    => $punchTime,
                            'fingerprint_id'   => $fingerprintId,
                        ]
                    );

                    $results['synced']++;
                    $results['logs'][] = "✓ {$member->full_name} — " . ($isLate ? 'Late' : 'Present') . " at {$punchTime->format('H:i')}";

                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['logs'][] = "Error processing record: " . $e->getMessage();
                    Log::error('Biometric sync error: ' . $e->getMessage());
                }
            }

            // Update device last synced time
            $device->update(['last_synced_at' => now()]);

            $results['success'] = true;
            $results['message'] = "Sync complete. {$results['synced']} records processed, {$results['errors']} errors.";

        } catch (\Exception $e) {
            $results['message'] = "Sync failed: " . $e->getMessage();
            Log::error('Biometric device sync failed: ' . $e->getMessage());
        }

        return $results;
    }

    public function testConnection(BiometricDevice $device): array
    {
        if (! $device->has_ip) {
            return ['success' => false, 'message' => self::NO_IP_MESSAGE];
        }

        try {
            $zk = $this->connect($device);

            if ($zk) {
                $zk->disconnect();
                return ['success' => true, 'message' => "Successfully connected to {$device->name}"];
            }

            return ['success' => false, 'message' => "Could not connect to {$device->name} at {$device->ip_address}:{$device->port}"];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => "Connection error: " . $e->getMessage()];
        }
    }

    /** A connected ZKTeco client, or null when the device doesn't answer. */
    protected function connect(BiometricDevice $device): ?ZKTeco
    {
        $zk = new ZKTeco($device->ip_address, (int) $device->port);

        return $zk->connect() ? $zk : null;
    }

    /** Switches the device back on and hangs up; each step is tried even if the one before failed. */
    private function release(ZKTeco $zk): void
    {
        try {
            $zk->enableDevice();
        } catch (\Throwable $e) {
            Log::error('Biometric device could not be re-enabled: ' . $e->getMessage());
        }

        try {
            $zk->disconnect();
        } catch (\Throwable $e) {
            Log::error('Biometric device disconnect failed: ' . $e->getMessage());
        }
    }
}
