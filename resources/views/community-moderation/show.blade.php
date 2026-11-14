@extends('layouts.app')

@section('title', 'Review ' . $type)

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('community-moderation.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to the queue
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Review {{ $type }} @include('community-moderation._item_status', ['item' => $item])</h2>
</div>

<div class="grid-main">
    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div style="font-size:13px; color:#64748b; margin-bottom:8px;">
                    By
                    @if($item->author)
                        <a href="{{ route('community-moderation.member', $item->author) }}">{{ $item->author->full_name }}</a>
                    @endif
                    · {{ $item->created_at?->format('j M Y, g:i A') }}
                </div>
                @if($type === 'comment' && $item->post)
                    <div style="font-size:13px; color:#64748b; margin-bottom:8px;">
                        On the post <a href="{{ route('community-moderation.show', ['post', $item->post_id]) }}">"{{ \Illuminate\Support\Str::limit($item->post->body ?? '(photos only)', 60) }}"</a>
                    </div>
                @endif
                <div data-field="body" style="font-size:15px; line-height:1.7; color:#1e293b;">{!! nl2br(e($item->body ?? '')) !!}</div>

                @if($type === 'post' && $item->photos->isNotEmpty())
                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                        @foreach($item->photos as $photo)
                            <a href="{{ route('community-moderation.photo', [$photo, 'full']) }}" target="_blank" rel="noopener">
                                <img src="{{ route('community-moderation.photo', [$photo, 'thumb']) }}" alt="" style="width:160px; height:120px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0;">
                            </a>
                        @endforeach
                    </div>
                @endif

                @if($item->isHidden())
                    <div style="margin-top:12px; font-size:13px; color:#dc2626;">
                        Hidden {{ $item->hidden_at->format('j M Y, g:i A') }}{{ $item->hiddenBy ? ' by ' . $item->hiddenBy->name : '' }}: {{ $item->hide_reason }}
                    </div>
                @endif
            </div>
        </div>

        @if($type === 'post' && $comments->isNotEmpty())
        <div class="card">
            <div class="card-header"><div class="card-title">Comments</div></div>
            <div class="card-body" style="padding:0;">
                @foreach($comments as $comment)
                    <div style="padding:10px 20px; border-bottom:1px solid #f1f5f9; font-size:13px;">
                        <strong>{{ $comment->author?->full_name }}</strong>
                        @include('community-moderation._item_status', ['item' => $comment])
                        <a href="{{ route('community-moderation.show', ['comment', $comment->id]) }}" style="margin-left:6px;">review</a>
                        <div style="color:#1e293b; margin-top:2px;">{{ $comment->body }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div>
        @unless($item->trashed())
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><div class="card-title">Actions</div></div>
            <div class="card-body">
                @if($item->isHidden())
                    <form method="POST" action="{{ route('community-moderation.unhide', [$type, $item->id]) }}" style="margin-bottom:12px;">
                        @csrf
                        <button type="submit" class="btn-outline"><i class="fas fa-eye"></i> Show it again</button>
                        <div style="font-size:12px; color:#94a3b8; margin-top:4px;">Open reports are dismissed.</div>
                    </form>
                @else
                    <form method="POST" action="{{ route('community-moderation.hide', [$type, $item->id]) }}" style="margin-bottom:12px;">
                        @csrf
                        <label class="form-label">Reason for hiding</label>
                        <input type="text" name="reason" maxlength="255" class="form-control {{ $errors->has('reason') ? 'is-invalid' : '' }}" placeholder="e.g. Breaks the guidelines: insults">
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button type="submit" class="btn-danger" style="margin-top:8px;"><i class="fas fa-eye-slash"></i> Hide</button>
                    </form>
                @endif
                @if($reports->where('status', 'open')->isNotEmpty())
                    <form method="POST" action="{{ route('community-moderation.dismiss', [$type, $item->id]) }}">
                        @csrf
                        <button type="submit" class="btn-outline"><i class="fas fa-check"></i> Dismiss open reports</button>
                    </form>
                @endif
            </div>
        </div>
        @endunless

        <div class="card">
            <div class="card-header"><div class="card-title">Reports ({{ $reports->count() }})</div></div>
            <div class="card-body" style="padding:0;">
                @forelse($reports as $report)
                    <div style="padding:10px 20px; border-bottom:1px solid #f1f5f9; font-size:13px;">
                        <strong>{{ $report->reasonLabel() }}</strong>
                        <span class="badge {{ $report->status === 'open' ? 'badge-warning' : 'badge-gray' }}">{{ ucfirst($report->status) }}</span>
                        <div style="color:#64748b;">{{ $report->reporter?->full_name }} · {{ $report->created_at->format('j M Y, g:i A') }}</div>
                        @if($report->details)<div style="margin-top:2px;">{{ $report->details }}</div>@endif
                    </div>
                @empty
                    <div style="padding:16px 20px; color:#94a3b8; font-size:13px;">No reports.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection
