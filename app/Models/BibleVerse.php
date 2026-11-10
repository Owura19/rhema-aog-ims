<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One KJV verse, cleaned (no pilcrows or italic brackets). Read-only data, written only by bible:import-kjv. */
class BibleVerse extends Model
{
    public $timestamps = false;

    protected $casts = [
        'book_id' => 'integer',
        'chapter' => 'integer',
        'verse'   => 'integer',
    ];

    public function book()
    {
        return $this->belongsTo(BibleBook::class, 'book_id');
    }
}
