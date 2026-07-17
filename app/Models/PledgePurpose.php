<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PledgePurpose extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── RELATIONSHIPS ───────────────────────────────────────────

    public function pledges()
    {
        return $this->hasMany(Pledge::class);
    }

    // ── SCOPES ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}