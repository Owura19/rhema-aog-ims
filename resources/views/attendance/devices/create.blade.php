@extends('layouts.app')

@section('title', 'Add Biometric Device')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('devices.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Devices
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Add Biometric Device</h2>
</div>

<div class="grid-main">

    <form method="POST" action="{{ route('devices.store') }}">
    @csrf

    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-fingerprint" style="color:#2563eb; margin-right:8px;"></i>Device Details</div>
        </div>
        <div class="card-body">
            <div class="grid-2">

                <div>
                    <label class="form-label">Device Name <span style="color:red;">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" placeholder="e.g. Main Entrance Device">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serial_number" value="{{ old('serial_number') }}" class="form-control {{ $errors->has('serial_number') ? 'is-invalid' : '' }}" placeholder="Printed on the device label">
                    @error('serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div style="grid-column:1 / -1; background:#dbeafe; border-radius:8px; padding:12px; font-size:13px; color:#1e40af;">
                    <i class="fas fa-info-circle"></i> <strong>Connects through the church bridge computer; IP not needed.</strong>
                    Leave IP and port empty, save, then open the device and generate its connection key.
                    Fill them in only if this server can reach the device directly (same network).
                </div>

                <div>
                    <label class="form-label">IP Address <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                    <input type="text" name="ip_address" value="{{ old('ip_address') }}" class="form-control {{ $errors->has('ip_address') ? 'is-invalid' : '' }}" placeholder="e.g. 192.168.1.100">
                    @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="form-label">Port <span style="color:#94a3b8; font-weight:400;">(with an IP)</span></label>
                    <input type="number" name="port" value="{{ old('port', 4370) }}" class="form-control {{ $errors->has('port') ? 'is-invalid' : '' }}" placeholder="4370">
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Default ZKTeco port is 4370</div>
                    @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div style="grid-column:1 / -1;">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" value="{{ old('location') }}" class="form-control" placeholder="e.g. Main Hall Entrance, Chapel Door">
                </div>

                <div style="grid-column:1 / -1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes about this device...">{{ old('notes') }}</textarea>
                </div>

                <div style="grid-column:1 / -1;">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} style="width:18px; height:18px;">
                        <span style="font-size:14px; font-weight:600; color:#374151;">Device is Active</span>
                    </label>
                </div>

            </div>
        </div>
    </div>

    <div class="toolbar" style="justify-content:flex-start;">
        <button type="submit" class="btn-primary toolbar-main">
            <i class="fas fa-save"></i> Add Device
        </button>
        <a href="{{ route('devices.index') }}" class="btn-outline">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>

    </form>

    <!-- Help Card -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-question-circle" style="color:#e8a020; margin-right:8px;"></i>Setup Guide</div>
            </div>
            <div class="card-body">
                <div style="font-size:13px; color:#374151; line-height:1.8;">
                    <div style="font-weight:700; margin-bottom:8px; color:#1e293b;">Through the church bridge computer</div>

                    <div style="margin-bottom:12px;">
                        <div style="font-weight:600; color:#2563eb;">1. Add the device here</div>
                        <div style="color:#64748b;">Name, serial number, location. No IP needed.</div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <div style="font-weight:600; color:#2563eb;">2. Generate the connection key</div>
                        <div style="color:#64748b;">On the device's page. It is shown once: copy it into the bridge program on the church PC.</div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <div style="font-weight:600; color:#2563eb;">3. Give members their ID</div>
                        <div style="color:#64748b;">Each member's "Fingerprint Device ID" is their user number on the device. Punches from unknown IDs are kept and listed on the device page.</div>
                    </div>

                    <div style="font-weight:700; margin:16px 0 8px; color:#1e293b;">Directly on the same network</div>
                    <div style="color:#64748b;">Give the device's IP (Menu → Comm → Ethernet) and port (4370 by default), then use Test connection and Sync from Device.</div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection
