@extends('layouts.app')

@section('title', 'Edit Service')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('services.show', $service) }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to {{ $service->name }}
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Service</h2>
</div>

<form method="POST" action="{{ route('services.update', $service) }}">
@csrf
@method('PUT')

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-church" style="color:#2563eb; margin-right:8px;"></i>Service Details</div>
    </div>
    <div class="card-body">
        <div class="grid-2">

            <div style="grid-column:span 2;">
                <label class="form-label">Service Name <span style="color:red;">*</span></label>
                <input type="text" name="name" value="{{ old('name', $service->name) }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Service Type <span style="color:red;">*</span></label>
                <select name="service_type" class="form-control">
                    @foreach(['Sunday Service','Midweek Service','Prayer Meeting','Special Event','Convention','Other'] as $type)
                        <option value="{{ $type }}" {{ old('service_type', $service->service_type) == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Status <span style="color:red;">*</span></label>
                <select name="status" class="form-control">
                    @foreach(['Scheduled','Ongoing','Completed','Cancelled'] as $status)
                        <option value="{{ $status }}" {{ old('status', $service->status) == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Service Date <span style="color:red;">*</span></label>
                <input type="date" name="service_date" value="{{ old('service_date', $service->service_date->format('Y-m-d')) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Venue</label>
                <input type="text" name="venue" value="{{ old('venue', $service->venue) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Start Time <span style="color:red;">*</span></label>
                <input type="time" name="start_time" value="{{ old('start_time', $service->start_time) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">End Time</label>
                <input type="time" name="end_time" value="{{ old('end_time', $service->end_time) }}" class="form-control">
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $service->description) }}</textarea>
            </div>

            <div style="grid-column:span 2;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="biometric_enabled" value="1" {{ old('biometric_enabled', $service->biometric_enabled) ? 'checked' : '' }} style="width:18px; height:18px;">
                    <span style="font-size:14px; font-weight:600; color:#374151;">Enable Biometric Attendance</span>
                </label>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Update Service
    </button>
    <a href="{{ route('services.show', $service) }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection