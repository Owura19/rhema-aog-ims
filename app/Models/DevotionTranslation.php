<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A devotion in one of the config('devotions.translations') languages. One per devotion per language. */
class DevotionTranslation extends Model
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'devotion_id',
        'locale',
        'title',
        'body',
        'prayer',
        'thought_for_the_day',
        'verse_text',
        'status',
        'ai_drafted',
        'source_hash',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'ai_drafted'  => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function devotion()
    {
        return $this->belongsTo(Devotion::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
