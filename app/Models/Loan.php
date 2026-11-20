<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A loan the church owes. Balance and repayments are kept by App\Services\Loans\LoanService. */
class Loan extends Model
{
    public const DEFAULT_TYPES = ['Project Offering', 'Harvest'];
    public const SOURCE_RECEIVED = 'received';
    public const SOURCE_FIXED = 'fixed';

    protected $fillable = [
        'lender', 'description', 'liability_account_id', 'opening_balance', 'opening_date',
        'opening_counter_account_id', 'opening_journal_entry_id', 'repayment_start_date', 'counted_types',
        'repayment_source', 'repayment_account_id', 'status', 'paid_off_at', 'created_by',
    ];

    protected $casts = [
        'opening_balance'      => 'decimal:2',
        'opening_date'         => 'date',
        'repayment_start_date' => 'date',
        'paid_off_at'          => 'date',
        'counted_types'        => 'array',
    ];

    public function repayments()
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function adjustments()
    {
        return $this->hasMany(LoanAdjustment::class);
    }

    public function events()
    {
        return $this->hasMany(LoanEvent::class);
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }

    public function counterAccount()
    {
        return $this->belongsTo(Account::class, 'opening_counter_account_id');
    }

    public function repaymentAccount()
    {
        return $this->belongsTo(Account::class, 'repayment_account_id');
    }

    /** Opening balance plus corrections (+ owed more, − owed less); manual repayments count as repaid. */
    public function owedTotal(): float
    {
        return (float) $this->opening_balance + (float) $this->adjustments()->where('kind', 'correction')->sum('amount');
    }

    public function repaidTotal(): float
    {
        return (float) $this->repayments()->sum('amount_applied') - (float) $this->adjustments()->where('kind', 'repayment')->sum('amount');
    }

    public function balance(): float
    {
        return round(max(0, $this->owedTotal() - $this->repaidTotal()), 2);
    }

    public function surplus(): float
    {
        return (float) $this->repayments()->sum('surplus');
    }

    public function progressPercent(): float
    {
        $owed = $this->owedTotal();

        return $owed > 0 ? min(100, round($this->repaidTotal() / $owed * 100, 1)) : 0;
    }

    public function counts(string $type): bool
    {
        return in_array($type, $this->counted_types ?? [], true);
    }
}
