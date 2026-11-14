@extends('layouts.app')

@section('title', $sermon->title)

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
        <a href="{{ route('sermons.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Sermons
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $sermon->title }}</h2>
        <div style="font-size:13px; color:#64748b;">{{ $sermon->preached_on->format('l j F Y') }} · @include('sermons._status', ['sermon' => $sermon])</div>
    </div>
    @can('manage sermons')
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('sermons.edit', $sermon) }}" class="btn-outline"><i class="fas fa-edit"></i> Edit</a>
        @if($sermon->isPublished())
            <form method="POST" action="{{ route('sermons.unpublish', $sermon) }}" onsubmit="return confirm(@js('Unpublish this sermon? It will be hidden from the app.'))">
                @csrf
                <button type="submit" class="btn-outline"><i class="fas fa-eye-slash"></i> Unpublish</button>
            </form>
        @else
            <form method="POST" action="{{ route('sermons.publish', $sermon) }}">
                @csrf
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Publish</button>
            </form>
        @endif
        <form method="POST" action="{{ route('sermons.destroy', $sermon) }}" onsubmit="return confirm(@js('Delete the sermon ' . $sermon->title . '?'))">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </form>
    </div>
    @endcan
</div>

<div class="grid-main">

    <div class="card">
        <div class="card-body" style="max-width:720px;">
            @if($sermon->thumbnailUrl())
                <img src="{{ $sermon->thumbnailUrl() }}" alt="" style="width:100%; max-width:480px; aspect-ratio:16/9; object-fit:cover; border-radius:10px; margin-bottom:16px;">
            @endif

            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                @if($sermon->youtubeUrl())
                    <a href="{{ $sermon->youtubeUrl() }}" target="_blank" rel="noopener noreferrer" class="btn-outline btn-sm"><i class="fab fa-youtube"></i> Watch on YouTube</a>
                @endif
                @if($sermon->audio_url)
                    <a href="{{ $sermon->audio_url }}" target="_blank" rel="noopener noreferrer" class="btn-outline btn-sm"><i class="fas fa-headphones"></i> Audio</a>
                @endif
                @if(! $sermon->youtubeUrl() && ! $sermon->audio_url)
                    <span style="font-size:13px; color:#94a3b8;"><i class="fas fa-circle-info"></i> No YouTube or audio link yet: add one before publishing.</span>
                @endif
            </div>

            @if($sermon->summary)
                <div data-field="summary" style="font-size:15px; line-height:1.8; color:#1e293b;">{!! nl2br(e($sermon->summary)) !!}</div>
            @else
                <div style="font-size:13px; color:#94a3b8;">No summary.</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-info-circle" style="color:#7c3aed; margin-right:8px;"></i>Details</div>
        </div>
        <div class="card-body" style="padding:0;">
            <table style="width:100%; font-size:13px;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Preacher</td>
                    <td style="padding:10px 20px;">
                        {{ $sermon->preacher_name }}
                        @if($sermon->preacherMember)
                            <div style="font-size:12px; color:#94a3b8;">Member: {{ $sermon->preacherMember->first_name }} {{ $sermon->preacherMember->last_name }}</div>
                        @endif
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Scripture</td>
                    <td style="padding:10px 20px;">{{ $sermon->scripture_reference ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Series</td>
                    <td style="padding:10px 20px;">{{ $sermon->series?->title ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Published</td>
                    <td style="padding:10px 20px;">{{ $sermon->isPublished() ? $sermon->published_at?->format('j M Y, g:i A') : 'Not yet' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Created</td>
                    <td style="padding:10px 20px;">{{ $sermon->createdBy?->name ?? '—' }}, {{ $sermon->created_at?->format('j M Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 20px; color:#94a3b8; font-weight:600;">Last edited</td>
                    <td style="padding:10px 20px;">{{ $sermon->updatedBy?->name ?? '—' }}, {{ $sermon->updated_at?->format('j M Y') }}</td>
                </tr>
            </table>
        </div>
    </div>

</div>

@endsection
