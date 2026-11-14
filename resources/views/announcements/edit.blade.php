@extends('layouts.app')

@section('title', 'Edit Announcement')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('announcements.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Announcements
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Announcement @include('announcements._state', ['announcement' => $announcement])</h2>
</div>

<form method="POST" action="{{ route('announcements.update', $announcement) }}">
    @csrf
    @method('PUT')
    @include('announcements._form')
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save changes</button>
</form>

@endsection
