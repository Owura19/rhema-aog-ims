<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A processed photo (JPEG, EXIF removed) of a community post, on the private 'community' disk. */
class CommunityPostPhoto extends Model
{
    public const VARIANT_FULL  = 'full';
    public const VARIANT_THUMB = 'thumb';

    protected $fillable = ['post_id', 'path', 'thumb_path', 'width', 'height', 'bytes', 'position'];

    protected $casts = [
        'width'    => 'integer',
        'height'   => 'integer',
        'bytes'    => 'integer',
        'position' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id')->withTrashed();
    }

    public function pathFor(string $variant): string
    {
        return $variant === self::VARIANT_THUMB ? $this->thumb_path : $this->path;
    }
}
