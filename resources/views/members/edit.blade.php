@extends('layouts.app')

@section('title', 'Edit Member')

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <a href="{{ route('members.show', $member) }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to {{ $member->full_name }}
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Member — {{ $member->full_name }}</h2>
    </div>
    <div style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:6px 16px; border-radius:20px;">
        {{ $member->member_id }}
    </div>
</div>

<form method="POST" action="{{ route('members.update', $member) }}" enctype="multipart/form-data">
@csrf
@method('PUT')

<!-- Personal Information -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user" style="color:#2563eb; margin-right:8px;"></i>Personal Information</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
                <label class="form-label">First Name <span style="color:red;">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $member->first_name) }}" class="form-control {{ $errors->has('first_name') ? 'is-invalid' : '' }}">
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Last Name <span style="color:red;">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name', $member->last_name) }}" class="form-control {{ $errors->has('last_name') ? 'is-invalid' : '' }}">
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Other Name</label>
                <input type="text" name="other_name" value="{{ old('other_name', $member->other_name) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Gender <span style="color:red;">*</span></label>
                <select name="gender" class="form-control {{ $errors->has('gender') ? 'is-invalid' : '' }}">
                    <option value="">Select gender</option>
                    <option value="Male" {{ old('gender', $member->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('gender', $member->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $member->date_of_birth?->format('Y-m-d')) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Marital Status</label>
                <select name="marital_status" class="form-control">
                    <option value="">Select status</option>
                    @foreach(['Single','Married','Divorced','Widowed'] as $status)
                        <option value="{{ $status }}" {{ old('marital_status', $member->marital_status) == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Occupation</label>
                <input type="text" name="occupation" value="{{ old('occupation', $member->occupation) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Employer</label>
                <input type="text" name="employer" value="{{ old('employer', $member->employer) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Photo</label>
                @if($member->photo)
                    <div style="margin-bottom:10px;">
                        <img src="{{ asset('storage/'.$member->photo) }}" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0;">
                        <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Current photo — upload new to replace</div>
                    </div>
                @endif
                <input type="file" name="photo" class="form-control" accept="image/*">
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

        </div>
    </div>
</div>

<!-- Contact Information -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-phone" style="color:#16a34a; margin-right:8px;"></i>Contact Information</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" value="{{ old('phone', $member->phone) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Alt. Phone Number</label>
                <input type="text" name="alt_phone" value="{{ old('alt_phone', $member->alt_phone) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Email Address</label>
                <input type="email" name="email" value="{{ old('email', $member->email) }}" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="grid-column:span 2;">
                <label class="form-label">Residential Address</label>
                <input type="text" name="residential_address" value="{{ old('residential_address', $member->residential_address) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Digital Address (Ghana Post GPS)</label>
                <input type="text" name="digital_address" value="{{ old('digital_address', $member->digital_address) }}" class="form-control">
            </div>

        </div>
    </div>
</div>

<!-- Emergency Contact -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-heartbeat" style="color:#dc2626; margin-right:8px;"></i>Emergency Contact</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">
            <div>
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $member->emergency_contact_name) }}" class="form-control">
            </div>
            <div>
                <label class="form-label">Emergency Contact Phone</label>
                <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $member->emergency_contact_phone) }}" class="form-control">
            </div>
        </div>
    </div>
</div>

<!-- Church Information -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-church" style="color:#7c3aed; margin-right:8px;"></i>Church Information</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
                <label class="form-label">Membership Status <span style="color:red;">*</span></label>
                <select name="membership_status" class="form-control {{ $errors->has('membership_status') ? 'is-invalid' : '' }}">
                    @foreach(['Active','Inactive','Visitor','Transferred','Deceased'] as $status)
                        <option value="{{ $status }}" {{ old('membership_status', $member->membership_status) == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
                @error('membership_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Member Type <span style="color:red;">*</span></label>
                <select name="member_type" class="form-control {{ $errors->has('member_type') ? 'is-invalid' : '' }}">
                    @foreach(['Full Member','Associate','Visitor'] as $type)
                        <option value="{{ $type }}" {{ old('member_type', $member->member_type) == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('member_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Date Joined</label>
                <input type="date" name="date_joined" value="{{ old('date_joined', $member->date_joined?->format('Y-m-d')) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Date Baptized</label>
                <input type="date" name="date_baptized" value="{{ old('date_baptized', $member->date_baptized?->format('Y-m-d')) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Family</label>
                <select name="family_id" class="form-control">
                    <option value="">No family assigned</option>
                    @foreach($families as $family)
                        <option value="{{ $family->id }}" {{ old('family_id', $member->family_id) == $family->id ? 'selected' : '' }}>{{ $family->family_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Family Role</label>
                <select name="family_role" class="form-control">
                    <option value="">Select role</option>
                    @foreach(['Head','Spouse','Child','Other'] as $role)
                        <option value="{{ $role }}" {{ old('family_role', $member->family_role) == $role ? 'selected' : '' }}>{{ $role }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Fingerprint Device ID</label>
                <input type="number" name="fingerprint_id" value="{{ old('fingerprint_id', $member->fingerprint_id) }}" class="form-control" placeholder="ZKTeco user ID">
                <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Assigned after fingerprint enrollment on device</div>
            </div>

        </div>
    </div>
</div>

<!-- Notes -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-sticky-note" style="color:#e8a020; margin-right:8px;"></i>Additional Notes</div>
    </div>
    <div class="card-body">
        <textarea name="notes" class="form-control" rows="4">{{ old('notes', $member->notes) }}</textarea>
    </div>
</div>

<!-- Action Buttons -->
<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Update Member
    </button>
    <a href="{{ route('members.show', $member) }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection