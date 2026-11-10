<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One (programme key, date) the weekly-programme generator has handled. Never deleted. */
class ProgramServiceLog extends Model
{
    public const CREATED  = 'created';    // the generator created the service
    public const EXISTING = 'existing';   // a service already existed at that date and time

    protected $table = 'program_service_log';

    protected $fillable = [
        'program_key',
        'service_date',
        'church_service_id',
        'outcome',
    ];

    protected $casts = [
        'service_date' => 'date',
    ];

    public function churchService()
    {
        return $this->belongsTo(ChurchService::class);
    }
}
