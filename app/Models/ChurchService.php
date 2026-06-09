<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChurchService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'service_type',
        'service_date',
        'start_time',
        'end_time',
        'venue',
        'description',
        'status',
        'biometric_enabled',
        'created_by',
    ];

    protected $casts = [
        'service_date'      => 'date',
        'biometric_enabled' => 'boolean',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function presentMembers()
    {
        return $this->hasMany(AttendanceLog::class)->where('status', 'Present');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function getAttendanceCountAttribute(): int
    {
        return $this->attendanceLogs()->whereIn('status', ['Present', 'Late'])->count();
    }

    public function getTotalMembersAttribute(): int
    {
        return Member::where('membership_status', 'Active')->count();
    }

    public function getAttendancePercentageAttribute(): float
    {
        $total = $this->total_members;
        if ($total === 0) return 0;
        return round(($this->attendance_count / $total) * 100, 1);
    }
}