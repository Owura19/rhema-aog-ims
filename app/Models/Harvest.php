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
        // Payments on this harvest's pledges. Cancelled pledges are left out,
        // the same as in Total Pledged, so Outstanding compares like with like.
        return (float) \App\Models\PledgePayment::whereIn(
            'pledge_id',
            $this->pledges()->where('status', '!=', 'Cancelled')->pluck('id')
        )->sum('amount');
    }

    // ── PERIOD (pledge_opens..harvest_date, or the whole year when unset) ──

    public function periodStart(): \Illuminate\Support\Carbon
    {
        return ($this->pledge_opens ?? \Illuminate\Support\Carbon::create($this->year, 1, 1))->copy()->startOfDay();
    }

    public function periodEnd(): \Illuminate\Support\Carbon
    {
        return ($this->harvest_date ?? \Illuminate\Support\Carbon::create($this->year, 12, 31))->copy()->startOfDay();
    }

    public function coversDate(\DateTimeInterface|string $date): bool
    {
        $day = \Illuminate\Support\Carbon::parse($date)->startOfDay();

        return $day->betweenIncluded($this->periodStart(), $this->periodEnd());
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