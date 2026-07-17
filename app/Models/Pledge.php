<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pledge extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference',
        'member_id',
        'pledger_name',
        'pledge_purpose_id',
        'harvest_id',
        'amount_pledged',
        'currency',
        'date_pledged',
        'target_date',
        'status',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'date_pledged'   => 'date',
        'target_date'    => 'date',
        'amount_pledged' => 'decimal:2',
    ];

    // ── RELATIONSHIPS ───────────────────────────────────────────

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function purpose()
    {
        return $this->belongsTo(PledgePurpose::class, 'pledge_purpose_id');
    }

    public function payments()
    {
        return $this->hasMany(PledgePayment::class);
    }

    public function harvest()
    {
        return $this->belongsTo(Harvest::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── ACCESSORS (calculated, never stored) ────────────────────

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->amount_pledged - $this->total_paid);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->amount_pledged <= 0) {
            return 0;
        }
        return min(100, round(($this->total_paid / $this->amount_pledged) * 100, 1));
    }

    public function getPledgerLabelAttribute(): string
    {
        if ($this->member) {
            return $this->member->full_name;
        }
        return $this->pledger_name ?? 'Unknown';
    }

    // ── HELPERS ─────────────────────────────────────────────────

    /**
     * Recalculate status based on payments and save if it changed.
     */
    public function refreshStatus(): void
    {
        if ($this->status === 'Cancelled') {
            return; // don't override a manual cancellation
        }

        $newStatus = $this->balance <= 0 ? 'Fulfilled' : 'Active';

        if ($this->status !== $newStatus) {
            $this->status = $newStatus;
            $this->save();
        }
    }

    // ── AUTO-GENERATE REFERENCE (PLG-00001) ─────────────────────

    protected static function booted(): void
    {
        static::creating(function ($pledge) {
            if (empty($pledge->reference)) {
                $latest = static::withTrashed()->latest('id')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $pledge->reference = 'PLG-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── SCOPES ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeFulfilled($query)
    {
        return $query->where('status', 'Fulfilled');
    }
}