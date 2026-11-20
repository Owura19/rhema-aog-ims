<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Services\Biometric\PunchProcessor;
use App\Services\BiometricService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BiometricDeviceController extends Controller
{
    protected BiometricService $biometricService;

    public function __construct(BiometricService $biometricService)
    {
        $this->biometricService = $biometricService;
    }

    public function index()
    {
        $devices = BiometricDevice::latest()->get();
        return view('attendance.devices.index', compact('devices'));
    }

    public function create()
    {
        return view('attendance.devices.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        BiometricDevice::create($validated);

        return redirect()->route('devices.index')
            ->with('success', 'Biometric device added successfully!');
    }

    public function edit(BiometricDevice $device)
    {
        return view('attendance.devices.edit', compact('device'));
    }

    public function update(Request $request, BiometricDevice $device)
    {
        $device->update($this->validated($request, $device));

        return redirect()->route('devices.index')
            ->with('success', 'Device updated successfully!');
    }

    public function destroy(BiometricDevice $device)
    {
        $device->delete();
        return redirect()->route('devices.index')
            ->with('success', 'Device removed.');
    }

    public function testConnection(BiometricDevice $device)
    {
        $result = $this->biometricService->testConnection($device);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function show(BiometricDevice $device)
    {
        return view('attendance.devices.show', $this->showData($device));
    }

    /**
     * Makes (or replaces) the device's connection key and shows it ONCE. The page is the POST's own
     * response, never a redirect with the key in the session, and is not cached. Only the key's
     * hash is stored; the old key stops working at once.
     */
    public function generateKey(BiometricDevice $device)
    {
        $replacing = $device->has_key;
        $key = $device->generateConnectionKey();

        return response()
            // (array_merge: the key must override showData()'s null default; with `+` the left side wins)
            ->view('attendance.devices.show', array_merge($this->showData($device->refresh()), ['newKey' => $key, 'replaced' => $replacing]))
            ->header('Cache-Control', 'no-store, private');
    }

    public function revokeKey(BiometricDevice $device)
    {
        $device->revokeConnectionKey();

        return redirect()->route('devices.show', $device)
            ->with('success', 'Connection key revoked. The bridge can no longer send punches for this device.');
    }

    /** Runs punches that were waiting (unmatched / no service) through attendance again. */
    public function reprocess(BiometricDevice $device, PunchProcessor $processor)
    {
        $r = $processor->reprocessWaiting($device);

        return redirect()->route('devices.show', $device)->with('success', $r['checked'] === 0
            ? 'No waiting punches.'
            : "{$r['checked']} waiting punches checked: {$r['resolved']} now on attendance, {$r['unmatched']} still unmatched, {$r['no_service']} still without a service.");
    }

    // ── helpers ────────────────────────────────────────────────────────

    /** IP and port are optional: a device behind the church bridge computer has no reachable address. */
    private function validated(Request $request, ?BiometricDevice $device = null): array
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('biometric_devices', 'serial_number')->ignore($device?->id)],
            'ip_address'    => 'nullable|ip',
            'port'          => 'nullable|required_with:ip_address|integer|min:1|max:65535',
            'location'      => 'nullable|string|max:255',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');
        if (blank($validated['ip_address'] ?? null)) {
            $validated['ip_address'] = $validated['port'] = null;
        }

        return $validated;
    }

    private function showData(BiometricDevice $device): array
    {
        $counts = $device->punches()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            'device'      => $device,
            'counts'      => $counts,
            'total'       => $counts->sum(),
            'recent'      => $device->punches()->with(['member', 'service'])->latest('punched_at')->latest('id')->limit(20)->get(),
            'unmatchedIds' => $device->punches()->where('status', BiometricPunch::UNMATCHED)
                ->selectRaw('device_user_id, COUNT(*) AS punches, MAX(punched_at) AS last_punch')
                ->groupBy('device_user_id')->orderByDesc('punches')->orderBy('device_user_id')->limit(50)->get(),
            'unmatchedIdCount' => $device->punches()->where('status', BiometricPunch::UNMATCHED)->distinct()->count('device_user_id'),
            'bridgeUrl'   => url('/api/v1/bridge'),
            'newKey'      => null,
            'replaced'    => false,
        ];
    }
}
