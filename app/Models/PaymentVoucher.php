<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentVoucher extends Model
{
    protected $fillable = [
        'voucher_no', 'voucher_date', 'payee', 'description',
        'category', 'account_id', 'cash_account_id',
        'amount', 'payment_method', 'cheque_number',
        'status',
        'prepared_by', 'approved_by', 'approved_at',
        'paid_by', 'paid_at', 'transaction_id', 'notes',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount'       => 'decimal:2',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
    ];

    // ── RELATIONSHIPS ─────────────────────────────────────────
    public function account()      { return $this->belongsTo(Account::class, 'account_id'); }
    public function cashAccount()  { return $this->belongsTo(Account::class, 'cash_account_id'); }
    public function preparedBy()   { return $this->belongsTo(User::class, 'prepared_by'); }
    public function approvedBy()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function paidBy()       { return $this->belongsTo(User::class, 'paid_by'); }
    public function transaction()  { return $this->belongsTo(Transaction::class, 'transaction_id'); }

    // ── HELPERS ───────────────────────────────────────────────
    public function getStatusColorAttribute(): string
    {
        return [
            'Pending'   => '#d97706',
            'Approved'  => '#2563eb',
            'Paid'      => '#16a34a',
            'Rejected'  => '#dc2626',
            'Cancelled' => '#6b7280',
        ][$this->status] ?? '#6b7280';
    }

    public function getCanApproveAttribute(): bool { return $this->status === 'Pending'; }
    public function getCanPayAttribute(): bool     { return $this->status === 'Approved'; }
}