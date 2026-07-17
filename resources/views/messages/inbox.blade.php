@extends('layouts.app')

@section('title', 'Member Messages')

@section('content')

<div style="margin-bottom:20px;">
    <h2 style="font-size:20px;font-weight:700;color:#1e293b;">Member Messages</h2>
    <p style="font-size:13px;color:#64748b;margin-top:4px;">Private messages from members. Click a conversation to read and reply.</p>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Latest Message</th>
                    <th>When</th>
                    <th style="text-align:center;">Unread</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($threads as $thread)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="member-avatar-placeholder" style="width:34px;height:34px;font-size:13px;">{{ strtoupper(substr($thread->member->first_name,0,1)) }}</div>
                            <div>
                                <div style="font-weight:600;color:#1e293b;font-size:13.5px;">{{ $thread->member->full_name }}</div>
                                <div style="font-size:11px;color:#94a3b8;">{{ $thread->member->member_id }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="max-width:340px;">
                        <div style="font-size:13px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            @if($thread->last)
                                <span style="color:#94a3b8;">{{ $thread->last->sender === 'member' ? '' : 'You: ' }}</span>{{ $thread->last->body }}
                            @endif
                        </div>
                    </td>
                    <td style="font-size:12.5px;color:#94a3b8;white-space:nowrap;">
                        {{ optional($thread->last)->created_at?->diffForHumans() }}
                    </td>
                    <td style="text-align:center;">
                        @if($thread->unread > 0)
                            <span class="badge badge-danger">{{ $thread->unread }} new</span>
                        @else
                            <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        <a href="{{ route('messages.thread', $thread->member) }}" class="btn-outline btn-sm"><i class="fas fa-comments"></i> Open</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:#94a3b8;padding:40px;">
                        <i class="fas fa-inbox" style="font-size:26px;color:#cbd5e1;"></i>
                        <div style="margin-top:10px;">No member messages yet.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection