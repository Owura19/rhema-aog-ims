<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventRsvp extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'member_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guests_count',
        'status',
        'notes',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function getAttendeeNameAttribute(): string
    {
        if ($this->member) {
            return $this->member->full_name;
        }
        return $this->guest_name ?? 'Guest';
    }
}