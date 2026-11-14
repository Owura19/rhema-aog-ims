<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A member's post in the community feed. Hidden posts stay visible to their author only. */
class CommunityPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'member_id',
        'body',
        'like_count',
        'comment_count',
        'report_count',
        'edited_at',
        'hidden_at',
        'hidden_by',
        'hide_reason',
        'hidden_automatically',
    ];

    protected $casts = [
        'edited_at'            => 'datetime',
        'hidden_at'            => 'datetime',
        'hidden_automatically' => 'boolean',
        'like_count'           => 'integer',
        'comment_count'        => 'integer',
        'report_count'         => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id')->withTrashed();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CommunityPostPhoto::class, 'post_id')->orderBy('position');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityLike::class, 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class, 'post_id');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommunityReport::class, 'target_id')->where('target_type', CommunityReport::TARGET_POST);
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
