<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The day-born audit trail: rows are only ever added, never edited or deleted. */
class DayBornOfferingChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['week_id', 'service_date', 'weekday', 'action', 'old_amount', 'new_amount', 'reason', 'changed_by'];

    protected $casts = [
        'service_date' => 'date',
        'weekday'      => 'integer',
        'old_amount'   => 'decimal:2',
        'new_amount'   => 'decimal:2',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
