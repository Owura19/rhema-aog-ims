@extends('layouts.app')

@section('title', 'Biometric Devices')

@section('content')

<div style="margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b;">Biometric Devices</h2>
        <div style="font-size:13px; color:#64748b;">Manage ZKTeco fingerprint devices for attendance</div>
    </div>
    <a href="{{ route('devices.create') }}" class="btn-primary">
        <i class="fas fa-plus"></i> Add Device
    </a>
</div>

@if($devices->isEmpty())
<div class="card">
    <div style="text-align:center; padding:60px; color:#94a3b8;">
        <i class="fas fa-fingerprint" style="font-size:48px; display:block; margin-bottom:16px;"></i>
        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No devices configured</div>
        <div style="margin-bottom:20px; font-size:13px;">Add your ZKTeco fingerprint device to enable biometric attendance</div>
        <a href="{{ route('devices.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Add First Device</a>
    </div>
</div>
@else
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">
    @foreach($devices as $device)
    <div class="card">
        <div class="card-body">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                <div style="width:48px; height:48px; background:{{ $device->is_active ? '#dcfce7' : '#f1f5f9' }}; border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-fingerprint" style="font-size:22px; color:{{ $device->is_active ? '#16a34a' : '#94a3b8' }};"></i>
                </div>
                @if($device->is_active)
                    <span class="badge badge-success">Active</span>
                @else
                    <span class="badge badge-gray">Inactive</span>
                @endif
            </div>

            <div style="font-size:16px; font-weight:700; color:#1e293b; margin-bottom:4px;">{{ $device->name }}</div>
            <div style="font-size:13px; color:#64748b; margin-bottom:16px;">{{ $device->location ?? 'No location set' }}</div>

            <div style="background:#f8fafc; border-radius:8px; padding:12px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span style="font-size:12px; color:#94a3b8;">IP Address</span>
                    <span style="font-size:13px; font-family:monospace; color:#1e293b;">{{ $device->ip_address }}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span style="font-size:12px; color:#94a3b8;">Port</span>
                    <span style="font-size:13px; font-family:monospace; color:#1e293b;">{{ $device->port }}</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="font-size:12px; color:#94a3b8;">Last Synced</span>
                    <span style="font-size:12px; color:#64748b;">{{ $device->last_synced_label }}</span>
                </div>
            </div>

            <div style="display:flex; gap:8px;">
                <form method="POST" action="{{ route('devices.test', $device) }}" style="flex:1;">
                    @csrf
                    <button type="submit" class="btn-outline btn-sm" style="width:100%;">
                        <i class="fas fa-plug"></i> Test
                    </button>
                </form>
                <a href="{{ route('devices.edit', $device) }}" class="btn-primary btn-sm">
                    <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="{{ route('devices.destroy', $device) }}" onsubmit="return confirm('Remove this device?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection