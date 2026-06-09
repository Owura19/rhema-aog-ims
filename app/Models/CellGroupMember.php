<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CellGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'cell_group_id',
        'member_id',
        'role',
        'joined_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'joined_date' => 'date',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function cellGroup()
    {
        return $this->belongsTo(CellGroup::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}