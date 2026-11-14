<?php

namespace App\Models;

use App\Support\Api\Money;
use App\Support\DayBorn\Ranking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One service date's day-born offering: 7 group totals, draft or published. Only
 * published weeks are shown to members. A separate tally from the finance records
 * unless posted to finance (transaction_id).
 */
class DayBornWeek extends Model
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'service_date',
        'special_service',
        'status',
        'notes',
        'published_at',
        'published_by',
        'transaction_id',
        'finance_account_id',
        'cash_account_id',
        'recorded_by',
        'updated_by',
    ];

    protected $casts = [
        'service_date'    => 'date',
        'special_service' => 'boolean',
        'published_at'    => 'datetime',
    ];

    public function offerings(): HasMany
    {
        return $this->hasMany(DayBornOffering::class, 'week_id')->orderBy('weekday');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(DayBornOfferingChange::class, 'week_id')->orderByDesc('id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** @return array<int, string> weekday => amount ("0.00" for a group with no row) */
    public function amounts(): array
    {
        $amounts = array_fill_keys(range(0, 6), '0.00');
        foreach ($this->offerings as $offering) {
            $amounts[$offering->weekday] = Money::format($offering->amount);
        }

        return $amounts;
    }

    /** The week's total, exact in pesewas ("1234.50"). */
    public function total(): string
    {
        return Money::fromCents(array_sum(array_map(fn ($amount) => Money::toCents($amount), $this->amounts())));
    }

    /** @return list<array{position: int, weekday: int, amount: string}> */
    public function ranking(): array
    {
        return Ranking::rank($this->amounts());
    }

    /** "service-date" route key: /day-born/2026-11-15 */
    public function getRouteKeyName(): string
    {
        return 'service_date';
    }

    public function getRouteKey(): string
    {
        return $this->service_date->toDateString();
    }
}
