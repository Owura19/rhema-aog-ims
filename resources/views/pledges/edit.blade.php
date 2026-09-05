@extends('layouts.app')
@section('title', 'Edit Pledge')
@section('content')
<div style="max-width:700px;">
    <a href="{{ route('pledges.show', $pledge) }}" style="color:#64748b; text-decoration:none; font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Pledge</a>
    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-pen" style="color:#7c3aed; margin-right:8px;"></i>Edit Pledge — {{ $pledge->reference }}</div>
        </div>
        <div class="card-body">
            @if($errors->any())
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
            @endif
            <form method="POST" action="{{ route('pledges.update', $pledge) }}">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:16px;">
                    <label class="form-label">Who is pledging?</label>
                    <div style="display:flex; gap:16px; margin-bottom:10px;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:14px; cursor:pointer;">
                            <input type="radio" name="pledger_type" value="member" {{ $pledge->member_id ? 'checked' : '' }} onclick="togglePledger('member')"> Registered Member
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-size:14px; cursor:pointer;">
                            <input type="radio" name="pledger_type" value="other" {{ !$pledge->member_id ? 'checked' : '' }} onclick="togglePledger('other')"> Non-member
                        </label>
                    </div>
                    <div id="member-select" style="display:{{ $pledge->member_id ? 'block' : 'none' }};">
                        <select name="member_id" class="form-control">
                            <option value="">— Select a member —</option>
                            @foreach($members as $member)
                            <option value="{{ $member->id }}" {{ old('member_id', $pledge->member_id)==$member->id ? 'selected' : '' }}>{{ $member->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="name-input" style="display:{{ !$pledge->member_id ? 'block' : 'none' }};">
                        <input type="text" name="pledger_name" class="form-control" placeholder="Enter pledger's name" value="{{ old('pledger_name', $pledge->pledger_name) }}">
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom:16px;">
                    <div>
                        <label class="form-label">Purpose</label>
                        <select name="pledge_purpose_id" class="form-control" required>
                            <option value="">— Select purpose —</option>
                            @foreach($purposes as $purpose)
                            <option value="{{ $purpose->id }}" {{ old('pledge_purpose_id', $pledge->pledge_purpose_id)==$purpose->id ? 'selected' : '' }}>{{ $purpose->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Amount Pledged (GHS)</label>
                        <input type="number" step="0.01" min="0.01" name="amount_pledged" class="form-control" value="{{ old('amount_pledged', $pledge->amount_pledged) }}" required>
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom:16px;">
                    <div>
                        <label class="form-label">Part of a Harvest campaign? <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                        <select name="harvest_id" class="form-control">
                            <option value="">— Not a harvest pledge —</option>
                            @foreach($harvests as $harvest)
                            <option value="{{ $harvest->id }}" {{ old('harvest_id', $pledge->harvest_id)==$harvest->id ? 'selected' : '' }}>{{ $harvest->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Service <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                        <select name="church_service_id" class="form-control">
                            <option value="">— Not tied to a service —</option>
                            @foreach($services as $service)
                            <option value="{{ $service->id }}" {{ old('church_service_id', $pledge->church_service_id)==$service->id ? 'selected' : '' }}>{{ $service->name }} ({{ \Carbon\Carbon::parse($service->service_date)->format('M d, Y') }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom:16px;">
                    <div>
                        <label class="form-label">Date Pledged</label>
                        <input type="date" name="date_pledged" class="form-control" value="{{ old('date_pledged', \Carbon\Carbon::parse($pledge->date_pledged)->format('Y-m-d')) }}" required>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" required>
                            @foreach(['Active','Fulfilled','Cancelled'] as $st)
                            <option value="{{ $st }}" {{ old('status', $pledge->status)==$st ? 'selected' : '' }}>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Target Date <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                    <input type="date" name="target_date" class="form-control" value="{{ old('target_date', $pledge->target_date ? \Carbon\Carbon::parse($pledge->target_date)->format('Y-m-d') : '') }}">
                </div>
                <div style="margin-bottom:20px;">
                    <label class="form-label">Notes <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $pledge->notes) }}</textarea>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Pledge</button>
                    <a href="{{ route('pledges.show', $pledge) }}" class="btn-outline" style="border-color:#e2e8f0; color:#64748b;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function togglePledger(type) {
        document.getElementById('member-select').style.display = (type === 'member') ? 'block' : 'none';
        document.getElementById('name-input').style.display    = (type === 'other')  ? 'block' : 'none';
    }
</script>
@endsection
