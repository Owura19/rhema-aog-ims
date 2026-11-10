<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One devotion per date. It is LIVE (visible in the app) when it is approved and
 * its publish_date has arrived; there is no separate "published" state.
 * The database allows only one non-deleted devotion per date (live_publish_date).
 */
class Devotion extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUSES        = [self::STATUS_DRAFT, self::STATUS_APPROVED];

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_AI     = 'ai';

    protected $fillable = [
        'publish_date',
        'title',
        'scripture_reference',
        'verse_text',
        'body',
        'prayer',
        'thought_for_the_day',
        'theme_id',
        'no_theme',
        'author_name',
        'status',
        'source',
        'ai_edited',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'approved_at'  => 'datetime',
        'no_theme'     => 'boolean',
        'ai_edited'    => 'boolean',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function theme()
    {
        return $this->belongsTo(DevotionTheme::class, 'theme_id');
    }

    public function translations()
    {
        return $this->hasMany(DevotionTranslation::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── SCOPES ─────────────────────────────────────────────

    /** Visible in the app: approved, dated today or earlier (soft-deleted rows are already excluded). */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_APPROVED)
            ->where('publish_date', '<=', today()->toDateString());
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** 'live' (approved, date reached), 'approved' (approved, date still ahead) or 'draft'. */
    public function state(): string
    {
        if (! $this->isApproved()) {
            return self::STATUS_DRAFT;
        }

        return $this->publish_date->lte(today()) ? 'live' : self::STATUS_APPROVED;
    }
}
