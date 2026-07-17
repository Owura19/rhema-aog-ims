<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_date',
        'description',
        'reference',
        'source_type',
        'source_id',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Total debits and credits for this entry (should always be equal)
    public function getTotalDebitAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function getIsBalancedAttribute(): bool
    {
        return round($this->total_debit, 2) === round($this->total_credit, 2);
    }
}