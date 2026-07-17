@extends('layouts.app')

@section('title', 'Conversation — ' . $member->full_name)

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('messages.inbox') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Messages</a>
</div>

<div class="card" style="max-width:760px;">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="member-avatar-placeholder" style="width:40px;height:40px;font-size:15px;">{{ strtoupper(substr($member->first_name,0,1)) }}</div>
            <div>
                <div class="card-title" style="margin:0;">{{ $member->full_name }}</div>
                <div style="font-size:12px;color:#94a3b8;">{{ $member->member_id }} · {{ $member->email }}</div>
            </div>
        </div>
        <a href="{{ route('members.show', $member) }}" style="font-size:13px;color:#2563eb;text-decoration:none;font-weight:600;">View profile</a>
    </div>

    @if(session('success'))
    <div style="margin:16px 22px 0;">
        <div class="alert alert-success"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>
    </div>
    @endif

    <!-- Conversation -->
    <div style="padding:22px;display:flex;flex-direction:column;gap:14px;min-height:220px;max-height:440px;overflow-y:auto;" id="thread">
        @forelse($messages as $msg)
            @if($msg->sender === 'member')
                <div style="align-self:flex-start;max-width:75%;background:#f1f5f9;color:#1e293b;padding:11px 15px;border-radius:14px;border-bottom-left-radius:4px;font-size:13.5px;line-height:1.5;">
                    {{ $msg->body }}
                    <div style="font-size:11px;margin-top:5px;color:#94a3b8;">{{ $member->first_name }} · {{ $msg->created_at->format('M d, g:i A') }}</div>
                </div>
            @else
                <div style="align-self:flex-end;max-width:75%;background:#1a3c5e;color:#fff;padding:11px 15px;border-radius:14px;border-bottom-right-radius:4px;font-size:13.5px;line-height:1.5;">
                    {{ $msg->body }}
                    <div style="font-size:11px;margin-top:5px;opacity:.7;">{{ optional($msg->senderUser)->name ?? 'Leadership' }} · {{ $msg->created_at->format('M d, g:i A') }}</div>
                </div>
            @endif
        @empty
            <div style="text-align:center;color:#94a3b8;padding:30px;font-size:13px;">No messages in this conversation.</div>
        @endforelse
    </div>

    <!-- Reply -->
    <form method="POST" action="{{ route('messages.reply', $member) }}" style="border-top:1px solid #f1f5f9;padding:16px 22px;display:flex;gap:10px;align-items:flex-end;">
        @csrf
        <textarea name="body" placeholder="Write a reply to {{ $member->first_name }}..." required
            style="flex:1;border:1.5px solid #e2e8f0;border-radius:10px;padding:11px 13px;font-size:13.5px;resize:vertical;min-height:46px;font-family:inherit;outline:none;"></textarea>
        <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Reply</button>
    </form>
    @error('body')<div style="color:#dc2626;font-size:12.5px;padding:0 22px 16px;">{{ $message }}</div>@enderror
</div>

<script>
    var t = document.getElementById('thread');
    if (t) t.scrollTop = t.scrollHeight;
</script>

@endsection