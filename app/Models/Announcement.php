<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A church announcement for the app's Home. LIVE = published, starts_at reached and
 * ends_at not yet reached (or none). Drafts and soft-deleted ones never reach the app.
 */
class Announcement extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /** The most the app is sent. */
    public const API_LIMIT = 10;

    protected $fillable = [
        'title',
        'body',
        'link_url',
        'is_pinned',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** What the app shows: published and inside its time window. Pinned first, then newest start. */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function scopeAppOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('starts_at')->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** draft | scheduled (published, not started) | active | expired (published, ended). */
    public function state(): string
    {
        return match (true) {
            ! $this->isPublished()                                 => 'draft',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at !== null && ! $this->ends_at->isFuture()    => 'expired',
            default                                                 => 'active',
        };
    }
}
