@extends('layouts.app')

@section('title', 'New Sermon')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('sermons.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Sermons
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">New Sermon</h2>
    <div style="font-size:13px; color:#64748b;">It is saved as a draft; publish it from its page to show it in the app.</div>
</div>

<form method="POST" action="{{ route('sermons.store') }}" enctype="multipart/form-data">
    @csrf
    @include('sermons._form')
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save draft</button>
</form>

@endsection
