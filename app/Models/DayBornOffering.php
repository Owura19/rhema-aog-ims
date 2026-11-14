<?php

namespace App\Models;

use App\Support\DayBorn\Weekdays;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One day-born group's total for a week (weekday 0 = Sunday ... 6 = Saturday). */
class DayBornOffering extends Model
{
    protected $fillable = ['week_id', 'weekday', 'amount', 'recorded_by', 'updated_by'];

    protected $casts = [
        'weekday' => 'integer',
        'amount'  => 'decimal:2',
    ];

    public function week(): BelongsTo
    {
        return $this->belongsTo(DayBornWeek::class, 'week_id');
    }

    public function label(): string
    {
        return Weekdays::label($this->weekday);
    }
}
