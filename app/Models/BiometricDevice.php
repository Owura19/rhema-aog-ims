<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BiometricDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_address',
        'port',
        'location',
        'is_active',
        'last_synced_at',
        'notes',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    // ── ACCESSORS ──────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    public function getLastSyncedLabelAttribute(): string
    {
        return $this->last_synced_at
            ? $this->last_synced_at->diffForHumans()
            : 'Never synced';
    }
}