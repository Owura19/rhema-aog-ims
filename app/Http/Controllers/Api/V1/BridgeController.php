<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateBridgeDevice;
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Services\Biometric\PunchProcessor;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The biometric bridge API (token-authenticated, see AuthenticateBridgeDevice):
 *   GET  /api/v1/bridge/ping     who am I talking to, and the server's clock
 *   POST /api/v1/bridge/punches  up to 500 punches; each is stored once and processed
 */
class BridgeController extends Controller
{
    public const MAX_PUNCHES = 500;

    public function ping(Request $request): JsonResponse
    {
        $device = $this->device($request);

        return response()->json([
            'device'        => $device->name,
            'server_time'   => now()->toIso8601String(),
            'last_punch_at' => $device->last_punch_at?->toIso8601String(),
        ]);
    }

    /**
     * Body: {"punches":[{"user_id":123,"timestamp":"2026-11-22 08:41:05","verify_type":1}, ...]}
     * Timestamps are the device's clock (Y-m-d H:i:s). Re-sending a punch already received is
     * counted as a duplicate and changes nothing.
     */
    public function punches(Request $request, PunchProcessor $processor): JsonResponse
    {
        $device = $this->device($request);

        $data = $request->validate([
            'punches'               => ['required', 'array', 'min:1', 'max:' . self::MAX_PUNCHES],
            'punches.*'             => ['required', 'array:user_id,timestamp,verify_type'],
            'punches.*.user_id'     => ['required', 'integer', 'min:0', 'max:4294967295'],
            'punches.*.timestamp'   => ['required', 'date_format:Y-m-d H:i:s', 'after:2000-01-01 00:00:00', 'before:' . now()->addDays(2)->format('Y-m-d H:i:s')],
            'punches.*.verify_type' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($device, $data, $processor) {
            $counts = ['received' => count($data['punches']), 'new' => 0, 'duplicates' => 0,
                BiometricPunch::MATCHED => 0, BiometricPunch::UNMATCHED => 0, BiometricPunch::NO_SERVICE => 0,
                BiometricPunch::DUPLICATE_IGNORED => 0, BiometricPunch::KEPT_MANUAL => 0];

            foreach ($data['punches'] as $row) {
                $punch = $this->store($device, $row);
                if (! $punch) {
                    $counts['duplicates']++;
                    continue;
                }

                $counts['new']++;
                $counts[$processor->process($punch)->status]++;
            }

            $device->forceFill(['last_punch_at' => $device->punches()->max('punched_at')])->save();

            return $counts;
        });

        return response()->json($result + ['last_punch_at' => $device->last_punch_at?->toIso8601String()]);
    }

    /** Saves a punch; null when this device already has it. */
    private function store(BiometricDevice $device, array $row): ?BiometricPunch
    {
        $keys = [
            'biometric_device_id' => $device->id,
            'device_user_id'      => (int) $row['user_id'],
            'punched_at'          => Carbon::createFromFormat('Y-m-d H:i:s', $row['timestamp'], config('app.timezone')),
        ];

        try {
            $punch = BiometricPunch::firstOrCreate($keys, ['verify_type' => $row['verify_type'] ?? null, 'status' => BiometricPunch::UNMATCHED]);
        } catch (UniqueConstraintViolationException) {
            return null;   // another request stored it between the check and the insert
        }

        return $punch->wasRecentlyCreated ? $punch : null;
    }

    private function device(Request $request): BiometricDevice
    {
        return $request->attributes->get(AuthenticateBridgeDevice::ATTRIBUTE);
    }
}
