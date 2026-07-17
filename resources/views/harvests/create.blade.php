@extends('layouts.app')

@section('title', 'New Harvest Campaign')

@section('content')

<div style="max-width:700px;">
    <a href="{{ route('harvests.index') }}" style="color:#64748b; text-decoration:none; font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Harvest Campaigns</a>

    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-wheat-awn" style="color:#ca8a04; margin-right:8px;"></i>Create Harvest Campaign</div>
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

            <form method="POST" action="{{ route('harvests.store') }}">
                @csrf

                <div style="margin-bottom:16px;">
                    <label class="form-label">Campaign Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. 2026 Annual Harvest" value="{{ old('name') }}" required>
                </div>

                <div class="grid-2" style="margin-bottom:16px;">
                    <div>
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ old('year', now()->year) }}" min="2000" max="2100" required>
                    </div>
                    <div>
                        <label class="form-label">Target Amount (GHS)</label>
                        <input type="number" step="0.01" min="0" name="target_amount" class="form-control" placeholder="0.00" value="{{ old('target_amount') }}" required>
                    </div>
                </div>

                <div class="grid-2" style="margin-bottom:16px;">
                    <div>
                        <label class="form-label">Pledging Opens <span style="color:#94a3b8; font-weight:400;">(e.g. August)</span></label>
                        <input type="date" name="pledge_opens" class="form-control" value="{{ old('pledge_opens') }}">
                    </div>
                    <div>
                        <label class="form-label">Harvest Date <span style="color:#94a3b8; font-weight:400;">(December)</span></label>
                        <input type="date" name="harvest_date" class="form-control" value="{{ old('harvest_date') }}">
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label class="form-label">Description <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Campaign</button>
                    <a href="{{ route('harvests.index') }}" class="btn-outline" style="border-color:#e2e8f0; color:#64748b;">Cancel</a>
                </div>
            </form>

        </div>
    </div>
</div>

@endsection