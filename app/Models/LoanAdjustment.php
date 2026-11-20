<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A manual change to a loan, with a reason. kind 'correction': the amount owed was wrong
 * (+ owed more, − owed less). kind 'repayment': paid some other way (always negative).
 */
class LoanAdjustment extends Model
{
    protected $fillable = [
        'loan_id', 'kind', 'amount', 'adjustment_date', 'reason', 'counter_account_id', 'journal_entry_id', 'created_by',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'adjustment_date' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function counterAccount()
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
