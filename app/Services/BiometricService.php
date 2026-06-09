<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\ChurchService;
use App\Models\Member;
use Rats\Zkteco\Lib\ZKTeco;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BiometricService
{
    public function syncDevice(BiometricDevice $device, ChurchService $service): array
    {
        $results = [
            'success'   => false,
            'synced'    => 0,
            'errors'    => 0,
            'message'   => '',
            'logs'      => [],
        ];

        try {
            $zk = new ZKTeco($device->ip_address, $device->port);

            if (!$zk->connect()) {
                $results['message'] = "Could not connect to device {$device->name} at {$device->ip_address}:{$device->port}";
                return $results;
            }

            // Get attendance records from device
            $zk->enableDevice();
            $attendance = $zk->getAttendance();
            $zk->disableDevice();
            $zk->disconnect();

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
        try {
            $zk = new ZKTeco($device->ip_address, $device->port);

            if ($zk->connect()) {
                $zk->disconnect();
                return ['success' => true, 'message' => "Successfully connected to {$device->name}"];
            }

            return ['success' => false, 'message' => "Could not connect to {$device->name} at {$device->ip_address}:{$device->port}"];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => "Connection error: " . $e->getMessage()];
        }
    }
}