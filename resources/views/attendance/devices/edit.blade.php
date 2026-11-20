@extends('layouts.app')

@section('title', 'Edit Device')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('devices.show', $device) }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to {{ $device->name }}
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Device — {{ $device->name }}</h2>
</div>

<form method="POST" action="{{ route('devices.update', $device) }}">
@csrf
@method('PUT')

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-fingerprint" style="color:#2563eb; margin-right:8px;"></i>Device Details</div>
    </div>
    <div class="card-body">
        <div class="grid-2">

            <div>
                <label class="form-label">Device Name <span style="color:red;">*</span></label>
                <input type="text" name="name" value="{{ old('name', $device->name) }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Serial Number</label>
                <input type="text" name="serial_number" value="{{ old('serial_number', $device->serial_number) }}" class="form-control {{ $errors->has('serial_number') ? 'is-invalid' : '' }}" placeholder="Printed on the device label">
                @error('serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:1 / -1; background:#dbeafe; border-radius:8px; padding:12px; font-size:13px; color:#1e40af;">
                <i class="fas fa-info-circle"></i> <strong>Connects through the church bridge computer; IP not needed.</strong>
                Fill in IP and port only if this server can reach the device directly.
            </div>

            <div>
                <label class="form-label">IP Address <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                <input type="text" name="ip_address" value="{{ old('ip_address', $device->ip_address) }}" class="form-control {{ $errors->has('ip_address') ? 'is-invalid' : '' }}">
                @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Port <span style="color:#94a3b8; font-weight:400;">(with an IP)</span></label>
                <input type="number" name="port" value="{{ old('port', $device->port) }}" class="form-control {{ $errors->has('port') ? 'is-invalid' : '' }}">
                @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:1 / -1;">
                <label class="form-label">Location</label>
                <input type="text" name="location" value="{{ old('location', $device->location) }}" class="form-control">
            </div>

            <div style="grid-column:1 / -1;">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $device->notes) }}</textarea>
            </div>

            <div style="grid-column:1 / -1;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $device->is_active) ? 'checked' : '' }} style="width:18px; height:18px;">
                    <span style="font-size:14px; font-weight:600; color:#374151;">Device is Active</span>
                </label>
                <div style="font-size:11px; color:#94a3b8; margin-top:4px;">An inactive device's connection key is refused until it is active again.</div>
            </div>

        </div>
    </div>
</div>

<div class="toolbar" style="justify-content:flex-start;">
    <button type="submit" class="btn-primary toolbar-main">
        <i class="fas fa-save"></i> Update Device
    </button>
    <a href="{{ route('devices.show', $device) }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection
