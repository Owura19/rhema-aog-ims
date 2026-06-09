<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_name',
        'address',
        'phone',
        'notes',
        'created_by',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function head()
    {
        return $this->hasOne(Member::class)->where('family_role', 'Head');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}