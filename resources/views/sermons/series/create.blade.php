@extends('layouts.app')

@section('title', 'New Sermon Series')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('sermon-series.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Series
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">New Series</h2>
</div>

<form method="POST" action="{{ route('sermon-series.store') }}">
    @csrf
    @include('sermons.series._form')
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save series</button>
</form>

@endsection
