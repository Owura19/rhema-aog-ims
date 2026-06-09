<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_service_id',
        'member_id',
        'status',
        'check_in_method',
        'check_in_time',
        'fingerprint_id',
        'notes',
        'marked_by',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function churchService()
    {
        return $this->belongsTo(ChurchService::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}