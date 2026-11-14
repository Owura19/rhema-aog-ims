<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A flat comment on a community post. Hidden comments stay visible to their author only. */
class CommunityComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'member_id',
        'body',
        'report_count',
        'hidden_at',
        'hidden_by',
        'hide_reason',
        'hidden_automatically',
    ];

    protected $casts = [
        'hidden_at'            => 'datetime',
        'hidden_automatically' => 'boolean',
        'report_count'         => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id')->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id')->withTrashed();
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommunityReport::class, 'target_id')->where('target_type', CommunityReport::TARGET_COMMENT);
    }

    public function scopeNotHidden(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }
}
