<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** A theme the pastor sets for a run of dates (inclusive). */
class DevotionTheme extends Model
{
    protected $fillable = [
        'title',
        'starts_on',
        'ends_on',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on'   => 'date',
    ];

    public function devotions()
    {
        return $this->hasMany(Devotion::class, 'theme_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The theme covering $date (starts_on <= date <= ends_on). When several overlap,
     * the one that started most recently wins (then the newest).
     */
    public static function covering(CarbonInterface|string $date): ?self
    {
        $day = Carbon::parse($date)->toDateString();

        return static::query()
            ->where('starts_on', '<=', $day)
            ->where('ends_on', '>=', $day)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }
}
