@extends('layouts.app')

@section('title', 'Community Moderation')

@section('content')

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-shield-halved" style="color:#2563eb; margin-right:8px;"></i>Reported in the app community</div>
        <span style="font-size:13px; color:#64748b;">{{ $items->count() }} item(s) waiting</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Author</th>
                    <th>Reports</th>
                    <th>Status</th>
                    <th style="text-align:right;">Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $row)
                <tr>
                    <td style="max-width:360px;">
                        <div style="font-size:12px; color:#94a3b8; text-transform:uppercase;">{{ $row['type'] }}</div>
                        <div style="color:#1e293b;">{{ \Illuminate\Support\Str::limit($row['item']->body ?? '(photos only)', 120) }}</div>
                    </td>
                    <td style="font-size:13px;">
                        @if($row['item']->author)
                            <a href="{{ route('community-moderation.member', $row['item']->author) }}">{{ $row['item']->author->full_name }}</a>
                        @endif
                    </td>
                    <td style="font-size:13px;">
                        <strong>{{ $row['count'] }}</strong>
                        <div style="font-size:12px; color:#64748b;">
                            @foreach($row['reasons'] as $label => $count){{ $label }} ({{ $count }})@if(! $loop->last), @endif @endforeach
                        </div>
                    </td>
                    <td>@include('community-moderation._item_status', ['item' => $row['item']])</td>
                    <td style="text-align:right;">
                        <a href="{{ route('community-moderation.show', [$row['type'], $row['id']]) }}" class="btn-outline btn-sm">Review</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">Nothing to review. Reports from the app appear here.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-ban" style="color:#dc2626; margin-right:8px;"></i>Members who can't post</div>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr><th>Member</th><th>Reason</th><th>Until</th></tr>
            </thead>
            <tbody>
                @forelse($bans as $ban)
                <tr>
                    <td><a href="{{ route('community-moderation.member', $ban->member_id) }}">{{ $ban->member?->full_name ?? 'Member #' . $ban->member_id }}</a></td>
                    <td style="font-size:13px;">{{ $ban->reason }}</td>
                    <td style="font-size:13px;">{{ $ban->banned_until ? $ban->banned_until->format('j M Y') : 'Until lifted' }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center; padding:24px; color:#94a3b8;">No one is banned.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
