<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CellGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'meeting_day',
        'meeting_time',
        'meeting_venue',
        'leader_id',
        'assistant_leader_id',
        'status',
        'created_by',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function leader()
    {
        return $this->belongsTo(Member::class, 'leader_id');
    }

    public function assistantLeader()
    {
        return $this->belongsTo(Member::class, 'assistant_leader_id');
    }

    public function cellGroupMembers()
    {
        return $this->hasMany(CellGroupMember::class);
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'cell_group_members')
                    ->withPivot('role', 'joined_date', 'status')
                    ->withTimestamps();
    }

    public function activeMembers()
    {
        return $this->members()->wherePivot('status', 'Active');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function getMemberCountAttribute(): int
    {
        return $this->activeMembers()->count();
    }

    // ── AUTO GENERATE CODE ─────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function ($group) {
            if (empty($group->code)) {
                $prefix = match($group->type) {
                    'Cell Group'  => 'CG',
                    'Department'  => 'DEPT',
                    'Ministry'    => 'MIN',
                    'Team'        => 'TEAM',
                    default       => 'GRP',
                };
                $latest = static::latest('id')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $group->code = $prefix . '-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}