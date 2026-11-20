<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A counted transaction's repayment of a loan (amount applied, and any surplus). */
class LoanRepayment extends Model
{
    public const FLAG_NO_LEDGER = 'no_ledger_entry';

    protected $fillable = [
        'loan_id', 'transaction_id', 'transaction_amount', 'amount_applied', 'surplus',
        'cash_account_id', 'journal_entry_id', 'flag',
    ];

    protected $casts = [
        'transaction_amount' => 'decimal:2',
        'amount_applied'     => 'decimal:2',
        'surplus'            => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }

    public function cashAccount()
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }
}
