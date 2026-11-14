<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A named run of sermons. */
class SermonSeries extends Model
{
    protected $table = 'sermon_series';

    protected $fillable = [
        'title',
        'description',
    ];

    public function sermons()
    {
        return $this->hasMany(Sermon::class, 'series_id');
    }
}
