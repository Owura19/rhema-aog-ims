@extends('layouts.app')

@section('title', 'Edit Devotion')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('devotions.show', $devotion) }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to the devotion
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Devotion</h2>
    @if($devotion->isApproved())
        @cannot('approve devotions')
            <div class="alert alert-warning" style="margin-top:10px;">
                <i class="fas fa-triangle-exclamation"></i> This devotion is approved. Saving changes returns it to draft until it is approved again.
            </div>
        @endcannot
    @endif
</div>

<form method="POST" action="{{ route('devotions.update', $devotion) }}">
    @csrf
    @method('PUT')
    @include('devotions._form')
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save changes</button>
</form>

@endsection
