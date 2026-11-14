@extends('layouts.app')

@section('title', 'Edit Sermon Series')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('sermon-series.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Series
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Series</h2>
</div>

<form method="POST" action="{{ route('sermon-series.update', $seriesItem) }}">
    @csrf
    @method('PUT')
    @include('sermons.series._form')
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save changes</button>
</form>

@endsection
