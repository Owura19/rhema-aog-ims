<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PledgePayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pledge_id',
        'amount',
        'payment_date',
        'payment_method',
        'transaction_id',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    // ── RELATIONSHIPS ───────────────────────────────────────────

    public function pledge()
    {
        return $this->belongsTo(Pledge::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── AUTO-POST TO FINANCE + UPDATE PLEDGE STATUS ─────────────

    protected static function booted(): void
    {
        // When a payment is recorded, create a matching income transaction
        // so it flows into finance reports and the Chart of Accounts.
        static::creating(function ($payment) {
            if (empty($payment->transaction_id)) {
                $pledge = $payment->pledge ?? Pledge::find($payment->pledge_id);

                if ($pledge) {
                    $transaction = Transaction::create([
                        'type'             => 'Pledge',
                        'subcategory'      => optional($pledge->purpose)->name, // e.g. Building Fund
                        'category'         => 'Income',
                        'amount'           => $payment->amount,
                        'currency'         => $pledge->currency ?? 'GHS',
                        'member_id'        => $pledge->member_id,
                        'payer_name'       => $pledge->pledger_name,
                        'transaction_date' => $payment->payment_date,
                        'payment_method'   => $payment->payment_method,
                        'status'           => 'Confirmed',
                        'description'      => 'Pledge payment — ' . $pledge->reference
                                              . ' (' . (optional($pledge->purpose)->name ?? 'Pledge') . ')',
                        'recorded_by'      => $payment->recorded_by,
                    ]);

                    $payment->transaction_id = $transaction->id;
                }
            }
        });

        // After a payment saves or is removed, refresh the pledge's status
        static::created(function ($payment) {
            optional($payment->pledge)->refreshStatus();
        });

        static::deleted(function ($payment) {
            // Also void the linked finance transaction so totals stay correct
            if ($payment->transaction) {
                $payment->transaction->delete();
            }
            optional($payment->pledge)->refreshStatus();
        });
    }
}