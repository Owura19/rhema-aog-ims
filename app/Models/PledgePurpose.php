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

    /** "Harvest", "Harvest 2026", ... — pledges for these must name a harvest campaign. */
    public function isHarvest(): bool
    {
        return stripos((string) $this->name, 'harvest') !== false;
    }

    // ── SCOPES ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}