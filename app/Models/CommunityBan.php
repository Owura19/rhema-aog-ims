<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member stopped from posting, commenting and liking: until banned_until, or until
 * lifted when banned_until is null. Their existing posts stay unless hidden one by one.
 */
class CommunityBan extends Model
{
    protected $fillable = ['member_id', 'reason', 'banned_by', 'banned_until', 'lifted_at', 'lifted_by'];

    protected $casts = [
        'banned_until' => 'datetime',
        'lifted_at'    => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    /** Not lifted, and either forever or not yet over. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at')
            ->where(fn (Builder $q) => $q->whereNull('banned_until')->orWhere('banned_until', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null && ($this->banned_until === null || $this->banned_until->isFuture());
    }

    /** The member's active ban that lasts longest (forever first), or null. */
    public static function activeFor(int $memberId): ?self
    {
        return static::active()
            ->where('member_id', $memberId)
            ->orderByRaw('banned_until is null desc')
            ->orderByDesc('banned_until')
            ->first();
    }
}
