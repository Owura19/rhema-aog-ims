<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberMessage;
use Illuminate\Http\Request;

class MemberMessageController extends Controller
{
    // ══════════════════════════════════════════════════════════
    //  MEMBER SIDE  (their own thread only)
    // ══════════════════════════════════════════════════════════

    /**
     * The logged-in member's own message thread.
     * SECURITY: uses auth()->user()->member — never a URL id.
     */
    public function memberThread()
    {
        $member = auth()->user()->member;
        if (!$member) {
            abort(403);
        }

        $messages = MemberMessage::where('member_id', $member->id)
            ->with('senderUser')
            ->orderBy('created_at')
            ->get();

        // Mark leader messages as read (the member is now viewing them)
        MemberMessage::where('member_id', $member->id)
            ->where('sender', 'leader')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('portal.messages', compact('member', 'messages'));
    }

    /**
     * Member sends a message.
     */
    public function memberSend(Request $request)
    {
        $member = auth()->user()->member;
        if (!$member) {
            abort(403);
        }

        $request->validate(['body' => 'required|string|max:2000']);

        MemberMessage::create([
            'member_id' => $member->id,
            'sender'    => 'member',
            'body'      => $request->body,
        ]);

        return back()->with('success', 'Message sent.');
    }

    // ══════════════════════════════════════════════════════════
    //  LEADERSHIP SIDE  (all threads)
    // ══════════════════════════════════════════════════════════

    /**
     * Inbox: list members who have message threads, newest activity first.
     */
    public function inbox()
    {
        // Threads grouped by member, with last message + unread count
        $threads = Member::whereHas('messages')
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->get()
            ->map(function ($member) {
                $last = $member->messages->first();
                $unread = MemberMessage::where('member_id', $member->id)
                    ->where('sender', 'member')
                    ->whereNull('read_at')
                    ->count();
                return (object) [
                    'member'  => $member,
                    'last'    => $last,
                    'unread'  => $unread,
                ];
            })
            ->sortByDesc(fn($t) => optional($t->last)->created_at)
            ->values();

        return view('messages.inbox', compact('threads'));
    }

    /**
     * Leadership views one member's thread.
     */
    public function show(Member $member)
    {
        $messages = MemberMessage::where('member_id', $member->id)
            ->with('senderUser')
            ->orderBy('created_at')
            ->get();

        // Mark member messages as read
        MemberMessage::where('member_id', $member->id)
            ->where('sender', 'member')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('messages.thread', compact('member', 'messages'));
    }

    /**
     * Leadership replies to a member.
     */
    public function reply(Request $request, Member $member)
    {
        $request->validate(['body' => 'required|string|max:2000']);

        MemberMessage::create([
            'member_id'      => $member->id,
            'sender'         => 'leader',
            'sender_user_id' => auth()->id(),
            'body'           => $request->body,
        ]);

        return back()->with('success', 'Reply sent.');
    }
}