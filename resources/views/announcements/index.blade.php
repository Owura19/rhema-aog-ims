@extends('layouts.app')

@section('title', 'Announcements')

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-bullhorn" style="color:#2563eb; margin-right:8px;"></i>Announcements</div>
        <a href="{{ route('announcements.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New Announcement</a>
    </div>

    <form method="GET" action="{{ route('announcements.index') }}" style="padding:12px 24px; display:flex; gap:10px; flex-wrap:wrap; border-bottom:1px solid #f1f5f9;">
        <select name="state" class="form-control" style="width:auto;">
            <option value="">All</option>
            @foreach(['active' => 'Showing in app', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'draft' => 'Drafts'] as $value => $label)
                <option value="{{ $value }}" {{ request('state') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
    </form>

    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Announcement</th>
                    <th>Shows</th>
                    <th>State</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($announcements as $announcement)
                <tr>
                    <td style="max-width:380px;">
                        <div style="font-weight:600; color:#1e293b;">
                            @if($announcement->is_pinned)<i class="fas fa-thumbtack" title="Pinned" style="color:#d97706; margin-right:4px;"></i>@endif
                            {{ $announcement->title }}
                        </div>
                        <div style="font-size:12px; color:#94a3b8;">{{ \Illuminate\Support\Str::limit($announcement->body, 110) }}</div>
                    </td>
                    <td style="font-size:13px; color:#64748b; white-space:nowrap;">
                        {{ $announcement->starts_at->format('j M Y, g:i A') }}
                        <div>{{ $announcement->ends_at ? 'until ' . $announcement->ends_at->format('j M Y, g:i A') : 'no end' }}</div>
                    </td>
                    <td>@include('announcements._state', ['announcement' => $announcement])</td>
                    <td style="text-align:right; white-space:nowrap;">
                        @if($announcement->isPublished())
                            <form method="POST" action="{{ route('announcements.unpublish', $announcement) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn-outline btn-sm"><i class="fas fa-eye-slash"></i> Unpublish</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('announcements.publish', $announcement) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm"><i class="fas fa-check"></i> Publish</button>
                            </form>
                        @endif
                        <a href="{{ route('announcements.edit', $announcement) }}" class="btn-outline btn-sm"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" style="display:inline;"
                              onsubmit="return confirm(@js('Delete the announcement ' . $announcement->title . '?'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center; padding:40px; color:#94a3b8;">No announcements.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($announcements->hasPages())
        <div style="padding:16px 24px;">{{ $announcements->links() }}</div>
    @endif
</div>

@endsection
