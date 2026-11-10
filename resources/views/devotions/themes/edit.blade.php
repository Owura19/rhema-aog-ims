@extends('layouts.app')

@section('title', 'Edit Devotion Theme')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('devotion-themes.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Themes
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Theme</h2>
</div>

<form method="POST" action="{{ route('devotion-themes.update', $theme) }}">
    @csrf
    @method('PUT')
    @include('devotions.themes._form')
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save changes</button>
</form>

@endsection
