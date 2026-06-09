<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Services\BiometricService;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port'       => 'required|integer|min:1|max:65535',
            'location'   => 'nullable|string|max:255',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

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
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port'       => 'required|integer|min:1|max:65535',
            'location'   => 'nullable|string|max:255',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $device->update($validated);

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
        return view('attendance.devices.show', compact('device'));
    }
}