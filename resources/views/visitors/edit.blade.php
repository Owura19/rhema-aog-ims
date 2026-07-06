@extends('layouts.app')

@section('title', 'Edit Visitor')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('visitors.show', $visitor) }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to {{ $visitor->full_name }}
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Visitor — {{ $visitor->full_name }}</h2>
</div>

<form method="POST" action="{{ route('visitors.update', $visitor) }}">
@csrf
@method('PUT')

<!-- Personal Info -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user" style="color:#2563eb; margin-right:8px;"></i>Personal Information</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
                <label class="form-label">First Name <span style="color:red;">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $visitor->first_name) }}" class="form-control {{ $errors->has('first_name') ? 'is-invalid' : '' }}">
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Last Name <span style="color:red;">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name', $visitor->last_name) }}" class="form-control {{ $errors->has('last_name') ? 'is-invalid' : '' }}">
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Gender</label>
                <select name="gender" class="form-control">
                    <option value="">Select gender</option>
                    <option value="Male" {{ old('gender', $visitor->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('gender', $visitor->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
            </div>

            <div>
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" value="{{ old('phone', $visitor->phone) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Email Address</label>
                <input type="email" name="email" value="{{ old('email', $visitor->email) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $visitor->date_of_birth?->format('Y-m-d')) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Marital Status</label>
                <select name="marital_status" class="form-control">
                    <option value="">Select status</option>
                    @foreach(['Single','Married','Divorced','Widowed'] as $status)
                        <option value="{{ $status }}" {{ old('marital_status', $visitor->marital_status) == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Occupation</label>
                <input type="text" name="occupation" value="{{ old('occupation', $visitor->occupation) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Address</label>
                <input type="text" name="address" value="{{ old('address', $visitor->address) }}" class="form-control">
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
                <input type="date" name="visit_date" value="{{ old('visit_date', $visitor->visit_date->format('Y-m-d')) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Visit Type <span style="color:red;">*</span></label>
                <select name="visit_type" class="form-control">
                    @foreach(['First Time','Second Time','Third Time','Regular'] as $type)
                        <option value="{{ $type }}" {{ old('visit_type', $visitor->visit_type) == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Related Service</label>
                <select name="church_service_id" class="form-control">
                    <option value="">Not linked to a service</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ old('church_service_id', $visitor->church_service_id) == $service->id ? 'selected' : '' }}>
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
                        <option value="{{ $option }}" {{ old('how_heard', $visitor->how_heard) == $option ? 'selected' : '' }}>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $visitor->notes) }}</textarea>
            </div>

        </div>
    </div>
</div>

<!-- Follow-up -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-phone" style="color:#e8a020; margin-right:8px;"></i>Follow-up Information</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
                <label class="form-label">Follow-up Status</label>
                <select name="follow_up_status" class="form-control">
                    @foreach(['Pending','Called','Visited','Attended Again','Joined','No Response','Not Interested'] as $status)
                        <option value="{{ $status }}" {{ old('follow_up_status', $visitor->follow_up_status) == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Follow-up Date</label>
                <input type="date" name="follow_up_date" value="{{ old('follow_up_date', $visitor->follow_up_date?->format('Y-m-d')) }}" class="form-control">
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Follow-up Notes</label>
                <textarea name="follow_up_notes" class="form-control" rows="3" placeholder="What happened during follow-up? Any prayer requests? Next steps?">{{ old('follow_up_notes', $visitor->follow_up_notes) }}</textarea>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Update Visitor
    </button>
    <a href="{{ route('visitors.show', $visitor) }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection