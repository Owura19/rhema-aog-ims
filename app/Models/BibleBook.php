<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One of the 66 books of the KJV. id = canonical position (Genesis 1 ... Revelation 66),
 * code = USFM code (e.g. JHN). Read-only data, written only by bible:import-kjv.
 */
class BibleBook extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'id'            => 'integer',
        'chapter_count' => 'integer',
        'verse_count'   => 'integer',
    ];

    public function verses()
    {
        return $this->hasMany(BibleVerse::class, 'book_id');
    }
}
