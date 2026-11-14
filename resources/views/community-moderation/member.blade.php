@extends('layouts.app')

@section('title', 'Community: ' . $member->full_name)

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('community-moderation.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to the queue
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">{{ $member->full_name }} in the community</h2>
    @if($activeBan)
        <div style="font-size:13px; color:#dc2626;">Can't post, comment or like {{ $activeBan->banned_until ? 'until ' . $activeBan->banned_until->format('j M Y') : 'until the ban is lifted' }}.</div>
    @endif
</div>

<div class="grid-main">
    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><div class="card-title">Posts ({{ $posts->count() }})</div></div>
            <div class="card-body" style="padding:0;">
                @forelse($posts as $post)
                    <div style="padding:10px 20px; border-bottom:1px solid #f1f5f9; font-size:13px;">
                        <a href="{{ route('community-moderation.show', ['post', $post->id]) }}">{{ $post->created_at->format('j M Y') }}</a>
                        @include('community-moderation._item_status', ['item' => $post])
                        <span style="color:#94a3b8;">{{ $post->report_count }} report(s)</span>
                        <div style="color:#1e293b;">{{ \Illuminate\Support\Str::limit($post->body ?? '(photos only)', 140) }}</div>
                    </div>
                @empty
                    <div style="padding:16px 20px; color:#94a3b8; font-size:13px;">No posts.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Comments ({{ $comments->count() }})</div></div>
            <div class="card-body" style="padding:0;">
                @forelse($comments as $comment)
                    <div style="padding:10px 20px; border-bottom:1px solid #f1f5f9; font-size:13px;">
                        <a href="{{ route('community-moderation.show', ['comment', $comment->id]) }}">{{ $comment->created_at->format('j M Y') }}</a>
                        @include('community-moderation._item_status', ['item' => $comment])
                        <div style="color:#1e293b;">{{ \Illuminate\Support\Str::limit($comment->body, 140) }}</div>
                    </div>
                @empty
                    <div style="padding:16px 20px; color:#94a3b8; font-size:13px;">No comments.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><div class="card-title">Stop from posting</div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('community-moderation.ban', $member) }}">
                    @csrf
                    <label class="form-label">Reason <span style="color:red;">*</span></label>
                    <textarea name="reason" rows="3" maxlength="500" class="form-control {{ $errors->has('reason') ? 'is-invalid' : '' }}">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <label class="form-label" style="margin-top:10px;">Until (leave empty: until lifted)</label>
                    <input type="date" name="until" value="{{ old('until') }}" class="form-control {{ $errors->has('until') ? 'is-invalid' : '' }}">
                    @error('until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div style="font-size:12px; color:#94a3b8; margin-top:4px;">Their existing posts stay visible unless you hide them one by one. They can still read, report and block.</div>
                    <button type="submit" class="btn-danger" style="margin-top:10px;"><i class="fas fa-ban"></i> Ban</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Ban history</div></div>
            <div class="card-body" style="padding:0;">
                @forelse($bans as $ban)
                    <div style="padding:10px 20px; border-bottom:1px solid #f1f5f9; font-size:13px;">
                        <strong>{{ $ban->created_at->format('j M Y') }}</strong>
                        → {{ $ban->banned_until ? $ban->banned_until->format('j M Y') : 'until lifted' }}
                        @if($ban->lifted_at)
                            <span class="badge badge-gray">Lifted {{ $ban->lifted_at->format('j M Y') }}{{ $ban->liftedBy ? ' by ' . $ban->liftedBy->name : '' }}</span>
                        @elseif($ban->isActive())
                            <span class="badge badge-danger">Active</span>
                        @else
                            <span class="badge badge-gray">Ended</span>
                        @endif
                        <div>{{ $ban->reason }}</div>
                        <div style="color:#94a3b8;">By {{ $ban->bannedBy?->name ?? '—' }}</div>
                        @if($ban->isActive())
                            <form method="POST" action="{{ route('community-moderation.lift-ban', $ban) }}" style="margin-top:6px;">
                                @csrf
                                <button type="submit" class="btn-outline btn-sm">Lift ban</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div style="padding:16px 20px; color:#94a3b8; font-size:13px;">Never banned.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection
