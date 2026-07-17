<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberMessage extends Model
{
    protected $fillable = [
        'member_id',
        'sender',
        'sender_user_id',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // ── RELATIONSHIPS ───────────────────────────────────────────

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function senderUser()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    // ── HELPERS ─────────────────────────────────────────────────

    public function getIsFromMemberAttribute(): bool
    {
        return $this->sender === 'member';
    }

    public function getIsFromLeaderAttribute(): bool
    {
        return $this->sender === 'leader';
    }
}