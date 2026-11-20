<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Audit row: what happened to a loan and who did it. */
class LoanEvent extends Model
{
    protected $fillable = ['loan_id', 'action', 'transaction_id', 'amount', 'note', 'user_id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }
}
