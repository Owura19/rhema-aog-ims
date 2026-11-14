<?php

namespace App\Models;

use App\Support\Sermons\YouTube;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sermon in the app's library. PUBLISHED = status published and published_at
 * reached; drafts and soft-deleted sermons never reach the app.
 */
class Sermon extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
        'preacher_name',
        'preacher_member_id',
        'preached_on',
        'scripture_reference',
        'bible_book_id',
        'chapter',
        'verse_start',
        'verse_end',
        'series_id',
        'summary',
        'youtube_video_id',
        'audio_url',
        'thumbnail_path',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'preached_on'   => 'date',
        'published_at'  => 'datetime',
        'bible_book_id' => 'integer',
        'chapter'       => 'integer',
        'verse_start'   => 'integer',
        'verse_end'     => 'integer',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function series()
    {
        return $this->belongsTo(SermonSeries::class, 'series_id');
    }

    public function preacherMember()
    {
        return $this->belongsTo(Member::class, 'preacher_member_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── SCOPES & HELPERS ───────────────────────────────────

    /** Visible in the app: published and published_at reached (soft-deleted rows are already excluded). */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function youtubeUrl(): ?string
    {
        return $this->youtube_video_id ? YouTube::watchUrl($this->youtube_video_id) : null;
    }

    /** The uploaded thumbnail, else the YouTube thumbnail, else null. */
    public function thumbnailUrl(): ?string
    {
        if ($this->thumbnail_path) {
            return asset('storage/' . $this->thumbnail_path);
        }

        return $this->youtube_video_id ? YouTube::thumbnailUrl($this->youtube_video_id) : null;
    }
}
