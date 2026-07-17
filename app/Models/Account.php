<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'code',
        'ref',
        'name',
        'type',
        'parent_id',
        'is_group',
        'is_active',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'is_group'  => 'boolean',
        'is_active' => 'boolean',
    ];

    // ── RELATIONSHIPS ───────────────────────────────────────────

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('sort_order');
    }

    // Transactions posted against this account (wired up in Phase 2)
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // ── ACCESSORS ───────────────────────────────────────────────

    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }

    /**
     * The "normal balance" side of this account type.
     * Assets & Expenses increase with DEBITS.
     * Liabilities, Equity & Income increase with CREDITS.
     * This is the core rule that makes double-entry work.
     */
    public function getNormalBalanceAttribute(): string
    {
        return in_array($this->type, ['Asset', 'Expense']) ? 'debit' : 'credit';
    }

    public function getIsDebitTypeAttribute(): bool
    {
        return $this->normal_balance === 'debit';
    }

    // ── SCOPES ──────────────────────────────────────────────────

    public function scopeIncome($query)
    {
        return $query->where('type', 'Income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'Expense');
    }

    public function scopeAsset($query)
    {
        return $query->where('type', 'Asset');
    }

    public function scopeLiability($query)
    {
        return $query->where('type', 'Liability');
    }

    public function scopeEquity($query)
    {
        return $query->where('type', 'Equity');
    }

    public function scopeGroups($query)
    {
        return $query->where('is_group', true);
    }

    public function scopePostable($query)
    {
        // Only non-group accounts can have transactions posted to them
        return $query->where('is_group', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}