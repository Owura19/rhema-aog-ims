@extends('layouts.app')

@section('title', 'New Devotion')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('devotions.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Devotions
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">New Devotion</h2>
    <div style="font-size:13px; color:#64748b;">Saved as a draft. It appears in the app only after it is approved.</div>
</div>

<form method="POST" action="{{ route('devotions.store') }}">
    @csrf
    @include('devotions._form')
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save draft</button>
</form>

@endsection
