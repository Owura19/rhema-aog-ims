<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'venue',
        'address',
        'capacity',
        'ticket_price',
        'is_free',
        'rsvp_required',
        'rsvp_deadline',
        'banner_image',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'rsvp_deadline' => 'date',
        'is_free'       => 'boolean',
        'rsvp_required' => 'boolean',
        'ticket_price'  => 'decimal:2',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function rsvps()
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function confirmedRsvps()
    {
        return $this->hasMany(EventRsvp::class)->where('status', 'Confirmed');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function getRsvpCountAttribute(): int
    {
        return $this->confirmedRsvps()->sum('guests_count');
    }

    public function getSpotsLeftAttribute(): ?int
    {
        if (!$this->capacity) return null;
        return max(0, $this->capacity - $this->rsvp_count);
    }

    public function getIsFullAttribute(): bool
    {
        if (!$this->capacity) return false;
        return $this->rsvp_count >= $this->capacity;
    }

    public function getDurationAttribute(): string
    {
        if (!$this->end_date || $this->start_date->eq($this->end_date)) {
            return $this->start_date->format('M d, Y');
        }
        return $this->start_date->format('M d') . ' — ' . $this->end_date->format('M d, Y');
    }
}