<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
    'reference',
    'type',
    'subcategory',
    'category',
    'amount',
    'currency',
    'member_id',
    'payer_name',
    'transaction_date',
    'church_service_id',
    'payment_method',
    'mobile_money_number',
    'cheque_number',
    'bank_name',
    'status',
    'description',
    'receipt_number',
    'recorded_by',
];

    protected $casts = [
        'transaction_date' => 'date',
        'amount'           => 'decimal:2',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function churchService()
    {
        return $this->belongsTo(ChurchService::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function getFormattedAmountAttribute(): string
    {
        return 'GHS ' . number_format($this->amount, 2);
    }

    public function getPayerLabelAttribute(): string
    {
        if ($this->member) {
            return $this->member->full_name;
        }
        return $this->payer_name ?? 'Anonymous';
    }

    // ── AUTO GENERATE REFERENCE ────────────────────────────

    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            if (empty($transaction->reference)) {
                $latest = static::withTrashed()->latest('id')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $transaction->reference = 'TXN-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── SCOPES ─────────────────────────────────────────────

    public function scopeIncome($query)
    {
        return $query->where('category', 'Income');
    }

    public function scopeExpense($query)
    {
        return $query->where('category', 'Expense');
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('transaction_date', now()->month)
                     ->whereYear('transaction_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('transaction_date', now()->year);
    }
}