@extends('layouts.app')

@section('title', 'Create Service')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('services.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Services
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Create New Service</h2>
</div>

<form method="POST" action="{{ route('services.store') }}">
@csrf

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-church" style="color:#2563eb; margin-right:8px;"></i>Service Details</div>
    </div>
    <div class="card-body">
        <div class="grid-2">

            <div style="grid-column:span 2;">
                <label class="form-label">Service Name <span style="color:red;">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" placeholder="e.g. Sunday Morning Service">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Service Type <span style="color:red;">*</span></label>
                <select name="service_type" class="form-control {{ $errors->has('service_type') ? 'is-invalid' : '' }}">
                    @foreach(['Sunday Service','Midweek Service','Prayer Meeting','Special Event','Convention','Other'] as $type)
                        <option value="{{ $type }}" {{ old('service_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('service_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Status <span style="color:red;">*</span></label>
                <select name="status" class="form-control">
                    @foreach(['Scheduled','Ongoing','Completed','Cancelled'] as $status)
                        <option value="{{ $status }}" {{ old('status', 'Scheduled') == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Service Date <span style="color:red;">*</span></label>
                <input type="date" name="service_date" value="{{ old('service_date', now()->format('Y-m-d')) }}" class="form-control {{ $errors->has('service_date') ? 'is-invalid' : '' }}">
                @error('service_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Venue</label>
                <input type="text" name="venue" value="{{ old('venue') }}" class="form-control" placeholder="e.g. Main Auditorium">
            </div>

            <div>
                <label class="form-label">Start Time <span style="color:red;">*</span></label>
                <input type="time" name="start_time" value="{{ old('start_time', '09:00') }}" class="form-control {{ $errors->has('start_time') ? 'is-invalid' : '' }}">
                @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">End Time</label>
                <input type="time" name="end_time" value="{{ old('end_time', '11:00') }}" class="form-control">
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Optional service description...">{{ old('description') }}</textarea>
            </div>

            <div style="grid-column:span 2;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="biometric_enabled" value="1" {{ old('biometric_enabled', true) ? 'checked' : '' }} style="width:18px; height:18px;">
                    <span style="font-size:14px; font-weight:600; color:#374151;">Enable Biometric Attendance for this service</span>
                </label>
                <div style="font-size:12px; color:#94a3b8; margin-top:4px; margin-left:28px;">When enabled, fingerprint device will sync attendance for this service</div>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Create Service
    </button>
    <a href="{{ route('services.index') }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection