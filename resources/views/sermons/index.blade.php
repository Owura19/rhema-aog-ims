@extends('layouts.app')

@section('title', 'Sermons')

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-microphone" style="color:#2563eb; margin-right:8px;"></i>Sermons</div>
        @can('manage sermons')
            <div style="display:flex; gap:8px;">
                <a href="{{ route('sermon-series.index') }}" class="btn-outline"><i class="fas fa-layer-group"></i> Series</a>
                <a href="{{ route('sermons.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New Sermon</a>
            </div>
        @endcan
    </div>

    <form method="GET" action="{{ route('sermons.index') }}" style="padding:12px 24px; display:flex; gap:10px; flex-wrap:wrap; border-bottom:1px solid #f1f5f9;">
        <select name="status" class="form-control" style="width:auto;">
            <option value="">All statuses</option>
            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Drafts</option>
            <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
        </select>
        <select name="series" class="form-control" style="width:auto;">
            <option value="">All series</option>
            @foreach($series as $item)
                <option value="{{ $item->id }}" {{ (string) request('series') === (string) $item->id ? 'selected' : '' }}>{{ $item->title }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
    </form>

    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Sermon</th>
                    <th>Preacher</th>
                    <th>Media</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sermons as $sermon)
                <tr>
                    <td style="font-size:13px; color:#64748b; white-space:nowrap;">{{ $sermon->preached_on->format('j M Y') }}</td>
                    <td>
                        <a href="{{ route('sermons.show', $sermon) }}" style="font-weight:600; color:#1e293b; text-decoration:none;">{{ $sermon->title }}</a>
                        @if($sermon->series || $sermon->scripture_reference)
                            <div style="font-size:12px; color:#94a3b8;">
                                {{ collect([$sermon->series?->title, $sermon->scripture_reference])->filter()->implode(' · ') }}
                            </div>
                        @endif
                    </td>
                    <td style="font-size:13px;">{{ $sermon->preacher_name }}</td>
                    <td style="font-size:13px; color:#64748b; white-space:nowrap;">
                        @if($sermon->youtube_video_id)<i class="fab fa-youtube" title="YouTube"></i>@endif
                        @if($sermon->audio_url)<i class="fas fa-headphones" title="Audio" style="margin-left:6px;"></i>@endif
                    </td>
                    <td>@include('sermons._status', ['sermon' => $sermon])</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">No sermons yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sermons->hasPages())
        <div style="padding:16px 24px;">{{ $sermons->links() }}</div>
    @endif
</div>

@endsection
