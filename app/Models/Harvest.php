<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Harvest extends Model
{
    protected $fillable = [
        'name',
        'year',
        'target_amount',
        'harvest_date',
        'pledge_opens',
        'status',
        'description',
    ];

    protected $casts = [
        'year'          => 'integer',
        'target_amount' => 'decimal:2',
        'harvest_date'  => 'date',
        'pledge_opens'  => 'date',
    ];

    // ── RELATIONSHIPS ───────────────────────────────────────────

    public function pledges()
    {
        return $this->hasMany(Pledge::class);
    }

    // ── COMPUTED TOTALS (live, from linked pledges) ─────────────

    public function getTotalPledgedAttribute(): float
    {
        return (float) $this->pledges()->where('status', '!=', 'Cancelled')->sum('amount_pledged');
    }

    public function getTotalPaidAttribute(): float
    {
        // Sum of all payments across this harvest's pledges
        return (float) \App\Models\PledgePayment::whereIn(
            'pledge_id',
            $this->pledges()->pluck('id')
        )->sum('amount');
    }

    public function getOutstandingAttribute(): float
    {
        return max(0, $this->total_pledged - $this->total_paid);
    }

    // Progress of actual money collected toward the target
    public function getTargetProgressAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }
        return min(100, round(($this->total_paid / $this->target_amount) * 100, 1));
    }

    // How much of the target has been pledged (committed) so far
    public function getPledgedProgressAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }
        return min(100, round(($this->total_pledged / $this->target_amount) * 100, 1));
    }

    public function getDaysToHarvestAttribute(): ?int
    {
        if (!$this->harvest_date) {
            return null;
        }
        return (int) now()->startOfDay()->diffInDays($this->harvest_date, false);
    }

    public function getPledgersCountAttribute(): int
    {
        return $this->pledges()->where('status', '!=', 'Cancelled')->count();
    }
}