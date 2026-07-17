@extends('layouts.app')

@section('title', 'Edit Harvest')

@section('content')

<div style="max-width:700px;">
    <a href="{{ route('harvests.show', $harvest) }}" style="color:#64748b; text-decoration:none; font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Campaign</a>

    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-wheat-awn" style="color:#ca8a04; margin-right:8px;"></i>Edit Harvest Campaign</div>
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

            <form method="POST" action="{{ route('harvests.update', $harvest) }}">
                @csrf
                @method('PUT')

                <div style="margin-bottom:16px;">
                    <label class="form-label">Campaign Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $harvest->name) }}" required>
                </div>

                <div class="grid-2" style="margin-bottom:16px;">
                    <div>
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ old('year', $harvest->year) }}" min="2000" max="2100" required>
                    </div>
                    <div>
                        <label class="form-label">Target Amount (GHS)</label>
                        <input type="number" step="0.01" min="0" name="target_amount" class="form-control" value="{{ old('target_amount', $harvest->target_amount) }}" required>
                    </div>
                </div>

                <div class="grid-2" style="margin-bottom:16px;">
                    <div>
                        <label class="form-label">Pledging Opens</label>
                        <input type="date" name="pledge_opens" class="form-control" value="{{ old('pledge_opens', optional($harvest->pledge_opens)->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">Harvest Date</label>
                        <input type="date" name="harvest_date" class="form-control" value="{{ old('harvest_date', optional($harvest->harvest_date)->format('Y-m-d')) }}">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" required>
                        <option value="Active"    {{ $harvest->status=='Active' ? 'selected' : '' }}>Active</option>
                        <option value="Completed" {{ $harvest->status=='Completed' ? 'selected' : '' }}>Completed</option>
                        <option value="Cancelled" {{ $harvest->status=='Cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div style="margin-bottom:20px;">
                    <label class="form-label">Description <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $harvest->description) }}</textarea>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    <a href="{{ route('harvests.show', $harvest) }}" class="btn-outline" style="border-color:#e2e8f0; color:#64748b;">Cancel</a>
                </div>
            </form>

        </div>
    </div>
</div>

@endsection