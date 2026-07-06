@extends('layouts.app')

@section('title', 'Record Visitor')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('visitors.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Visitors
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Record New Visitor</h2>
</div>

<form method="POST" action="{{ route('visitors.store') }}">
@csrf

<!-- Personal Info -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user" style="color:#2563eb; margin-right:8px;"></i>Personal Information</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
                <label class="form-label">First Name <span style="color:red;">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control {{ $errors->has('first_name') ? 'is-invalid' : '' }}" placeholder="First name">
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Last Name <span style="color:red;">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control {{ $errors->has('last_name') ? 'is-invalid' : '' }}" placeholder="Last name">
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Gender</label>
                <select name="gender" class="form-control">
                    <option value="">Select gender</option>
                    <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
            </div>

            <div>
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="e.g. 0244000000">
            </div>

            <div>
                <label class="form-label">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="email@example.com">
            </div>

            <div>
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Marital Status</label>
                <select name="marital_status" class="form-control">
                    <option value="">Select status</option>
                    @foreach(['Single','Married','Divorced','Widowed'] as $status)
                        <option value="{{ $status }}" {{ old('marital_status') == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Occupation</label>
                <input type="text" name="occupation" value="{{ old('occupation') }}" class="form-control" placeholder="e.g. Teacher, Nurse">
            </div>

            <div>
                <label class="form-label">Address</label>
                <input type="text" name="address" value="{{ old('address') }}" class="form-control" placeholder="Home address">
            </div>

        </div>
    </div>
</div>

<!-- Visit Details -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-church" style="color:#7c3aed; margin-right:8px;"></i>Visit Details</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
                <label class="form-label">Visit Date <span style="color:red;">*</span></label>
                <input type="date" name="visit_date" value="{{ old('visit_date', now()->format('Y-m-d')) }}" class="form-control {{ $errors->has('visit_date') ? 'is-invalid' : '' }}">
                @error('visit_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Visit Type <span style="color:red;">*</span></label>
                <select name="visit_type" class="form-control">
                    @foreach(['First Time','Second Time','Third Time','Regular'] as $type)
                        <option value="{{ $type }}" {{ old('visit_type', 'First Time') == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Related Service</label>
                <select name="church_service_id" class="form-control">
                    <option value="">Not linked to a service</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ old('church_service_id') == $service->id ? 'selected' : '' }}>
                            {{ $service->name }} — {{ $service->service_date->format('M d, Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">How Did They Hear About Us?</label>
                <select name="how_heard" class="form-control">
                    <option value="">Select option</option>
                    @foreach(['Friend/Family','Social Media','Flyer/Banner','Radio/TV','Walked In','Online','Other'] as $option)
                        <option value="{{ $option }}" {{ old('how_heard') == $option ? 'selected' : '' }}>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes about this visitor...">{{ old('notes') }}</textarea>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Record Visitor
    </button>
    <a href="{{ route('visitors.index') }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection