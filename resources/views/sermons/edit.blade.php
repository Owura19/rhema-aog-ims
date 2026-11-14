@extends('layouts.app')

@section('title', 'Edit Sermon')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('sermons.show', $sermon) }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to the sermon
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Sermon</h2>
    @if($sermon->isPublished())
        <div style="font-size:13px; color:#64748b;">This sermon is published: changes show in the app as soon as you save.</div>
    @endif
</div>

<form method="POST" action="{{ route('sermons.update', $sermon) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('sermons._form')
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save changes</button>
</form>

@endsection
