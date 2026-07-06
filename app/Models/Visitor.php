<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visitor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'gender',
        'date_of_birth',
        'address',
        'occupation',
        'marital_status',
        'how_heard',
        'church_service_id',
        'visit_date',
        'visit_type',
        'follow_up_status',
        'follow_up_date',
        'follow_up_notes',
        'followed_up_by',
        'converted_to_member',
        'member_id',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'visit_date'           => 'date',
        'follow_up_date'       => 'date',
        'date_of_birth'        => 'date',
        'converted_to_member'  => 'boolean',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function churchService()
    {
        return $this->belongsTo(ChurchService::class);
    }

    public function followedUpBy()
    {
        return $this->belongsTo(User::class, 'followed_up_by');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getFollowUpStatusColorAttribute(): string
    {
        return match($this->follow_up_status) {
            'Pending'         => 'warning',
            'Called'          => 'info',
            'Visited'         => 'info',
            'Attended Again'  => 'success',
            'Joined'          => 'success',
            'No Response'     => 'danger',
            'Not Interested'  => 'gray',
            default           => 'gray',
        };
    }
}