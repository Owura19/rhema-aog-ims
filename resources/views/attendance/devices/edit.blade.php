@extends('layouts.app')

@section('title', 'Edit Device')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('devices.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Devices
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

            <div style="grid-column:span 2;">
                <label class="form-label">Device Name <span style="color:red;">*</span></label>
                <input type="text" name="name" value="{{ old('name', $device->name) }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">IP Address <span style="color:red;">*</span></label>
                <input type="text" name="ip_address" value="{{ old('ip_address', $device->ip_address) }}" class="form-control {{ $errors->has('ip_address') ? 'is-invalid' : '' }}">
                @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Port <span style="color:red;">*</span></label>
                <input type="number" name="port" value="{{ old('port', $device->port) }}" class="form-control">
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Location</label>
                <input type="text" name="location" value="{{ old('location', $device->location) }}" class="form-control">
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $device->notes) }}</textarea>
            </div>

            <div style="grid-column:span 2;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $device->is_active) ? 'checked' : '' }} style="width:18px; height:18px;">
                    <span style="font-size:14px; font-weight:600; color:#374151;">Device is Active</span>
                </label>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Update Device
    </button>
    <a href="{{ route('devices.index') }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection