<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'content',
        'type',
        'media_path',
        'media_thumbnail',
        'visibility',
        'is_pinned',
        'is_approved',
        'likes_count',
        'comments_count',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'is_approved'  => 'boolean',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class)->whereNull('parent_id')->latest();
    }

    public function allComments()
    {
        return $this->hasMany(PostComment::class)->latest();
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function getMediaUrlAttribute(): ?string
    {
        return $this->media_path ? asset('storage/' . $this->media_path) : null;
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}