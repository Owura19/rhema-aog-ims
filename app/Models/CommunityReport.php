<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member's report of a post or comment. One per reporter per item. */
class CommunityReport extends Model
{
    public const TARGET_POST    = 'post';
    public const TARGET_COMMENT = 'comment';

    public const STATUS_OPEN      = 'open';
    public const STATUS_ACTIONED  = 'actioned';
    public const STATUS_DISMISSED = 'dismissed';

    /** reason => label for moderators and the app */
    public const REASONS = [
        'spam'              => 'Spam or scam',
        'harassment'        => 'Harassment or bullying',
        'hate'              => 'Hate or insults',
        'sexual'            => 'Sexual content',
        'violence'          => 'Violence or threats',
        'privacy'           => "Shares someone's private information",
        'false_information' => 'False information',
        'other'             => 'Something else',
    ];

    protected $fillable = [
        'reporter_member_id',
        'target_type',
        'target_id',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'target_id'   => 'integer',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reporter_member_id')->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeFor(Builder $query, string $type, int $id): Builder
    {
        return $query->where('target_type', $type)->where('target_id', $id);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
