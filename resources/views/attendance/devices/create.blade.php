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

                <div style="grid-column:span 2;">
                    <label class="form-label">Device Name <span style="color:red;">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" placeholder="e.g. Main Entrance Device">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="form-label">IP Address <span style="color:red;">*</span></label>
                    <input type="text" name="ip_address" value="{{ old('ip_address') }}" class="form-control {{ $errors->has('ip_address') ? 'is-invalid' : '' }}" placeholder="e.g. 192.168.1.100">
                    @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="form-label">Port <span style="color:red;">*</span></label>
                    <input type="number" name="port" value="{{ old('port', 4370) }}" class="form-control {{ $errors->has('port') ? 'is-invalid' : '' }}" placeholder="4370">
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Default ZKTeco port is 4370</div>
                    @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div style="grid-column:span 2;">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" value="{{ old('location') }}" class="form-control" placeholder="e.g. Main Hall Entrance, Chapel Door">
                </div>

                <div style="grid-column:span 2;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes about this device...">{{ old('notes') }}</textarea>
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} style="width:18px; height:18px;">
                        <span style="font-size:14px; font-weight:600; color:#374151;">Device is Active</span>
                    </label>
                </div>

            </div>
        </div>
    </div>

    <div style="display:flex; gap:12px;">
        <button type="submit" class="btn-primary">
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
                    <div style="font-weight:700; margin-bottom:8px; color:#1e293b;">ZKTeco Device Setup</div>

                    <div style="margin-bottom:12px;">
                        <div style="font-weight:600; color:#2563eb;">1. Connect to network</div>
                        <div style="color:#64748b;">Connect your ZKTeco device to the same WiFi/LAN network as this server.</div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <div style="font-weight:600; color:#2563eb;">2. Find IP address</div>
                        <div style="color:#64748b;">On the device: Menu → Comm → Ethernet → IP Address</div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <div style="font-weight:600; color:#2563eb;">3. Enable TCP/IP</div>
                        <div style="color:#64748b;">Ensure TCP/IP communication is enabled on the device settings.</div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <div style="font-weight:600; color:#2563eb;">4. Default port</div>
                        <div style="color:#64748b;">ZKTeco devices use port <strong>4370</strong> by default. Only change if modified on device.</div>
                    </div>

                    <div style="background:#dbeafe; border-radius:8px; padding:12px; margin-top:16px;">
                        <div style="font-weight:600; color:#1e40af; margin-bottom:4px;"><i class="fas fa-info-circle"></i> Recommended devices</div>
                        <div style="color:#1e40af; font-size:12px;">ZKTeco K40, ZK400, iClock 360 — available in Accra electronics markets.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection