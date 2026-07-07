@extends('layouts.app')

@section('title', 'Create Group')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('cellgroups.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Groups
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Create New Group</h2>
</div>

<form method="POST" action="{{ route('cellgroups.store') }}">
@csrf

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users" style="color:#2563eb; margin-right:8px;"></i>Group Details</div>
    </div>
    <div class="card-body">
        <div class="grid-3">

            <div style="grid-column:span 2;">
                <label class="form-label">Group Name <span style="color:red;">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" placeholder="e.g. Choir Department, Monday Cell Group">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Type <span style="color:red;">*</span></label>
                <select name="type" class="form-control {{ $errors->has('type') ? 'is-invalid' : '' }}">
                    @foreach(['Cell Group','Department','Ministry','Team'] as $type)
                        <option value="{{ $type }}" {{ old('type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Leader</label>
                <select name="leader_id" class="form-control">
                    <option value="">No leader assigned</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" {{ old('leader_id') == $member->id ? 'selected' : '' }}>
                            {{ $member->full_name }} ({{ $member->member_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Assistant Leader</label>
                <select name="assistant_leader_id" class="form-control">
                    <option value="">None</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" {{ old('assistant_leader_id') == $member->id ? 'selected' : '' }}>
                            {{ $member->full_name }} ({{ $member->member_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Status <span style="color:red;">*</span></label>
                <select name="status" class="form-control">
                    <option value="Active" {{ old('status', 'Active') == 'Active' ? 'selected' : '' }}>Active</option>
                    <option value="Inactive" {{ old('status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Brief description of this group or department...">{{ old('description') }}</textarea>
            </div>

        </div>
    </div>
</div>

<!-- Meeting Details -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-calendar" style="color:#7c3aed; margin-right:8px;"></i>Meeting Details</div>
    </div>
    <div class="card-body">
        <div class="grid-3">

            <div>
                <label class="form-label">Meeting Day</label>
                <select name="meeting_day" class="form-control">
                    <option value="">Select day</option>
                    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)
                        <option value="{{ $day }}" {{ old('meeting_day') == $day ? 'selected' : '' }}>{{ $day }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Meeting Time</label>
                <input type="time" name="meeting_time" value="{{ old('meeting_time') }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Meeting Venue</label>
                <input type="text" name="meeting_venue" value="{{ old('meeting_venue') }}" class="form-control" placeholder="e.g. Main Hall, Member's Home">
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Create Group
    </button>
    <a href="{{ route('cellgroups.index') }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

@endsection