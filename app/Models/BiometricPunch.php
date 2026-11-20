<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One punch received from a device through the bridge. Kept as received; status says what the
 * app did with it (see App\Services\Biometric\PunchProcessor).
 */
class BiometricPunch extends Model
{
    public const MATCHED           = 'matched';
    public const UNMATCHED         = 'unmatched';
    public const NO_SERVICE        = 'no_service';
    public const DUPLICATE_IGNORED = 'duplicate_ignored';
    public const KEPT_MANUAL       = 'kept_manual';

    /** Punches that can be processed again once a member gets an ID or a service exists. */
    public const WAITING = [self::UNMATCHED, self::NO_SERVICE];

    protected $fillable = [
        'biometric_device_id', 'device_user_id', 'punched_at', 'verify_type', 'status',
        'member_id', 'service_id', 'attendance_log_id', 'processed_at',
    ];

    protected $casts = [
        'punched_at'   => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function service()
    {
        return $this->belongsTo(ChurchService::class, 'service_id');
    }

    public function attendanceLog()
    {
        return $this->belongsTo(AttendanceLog::class);
    }
}
