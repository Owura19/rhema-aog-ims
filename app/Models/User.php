<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /** Mobile app token lifetimes (Sanctum's 180-day expiration is the backstop). */
    public const MEMBER_TOKEN_DAYS = 90;
    public const STAFF_TOKEN_DAYS  = 30;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'login_phone',
        'password',
        'is_active',
        'member_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            // Not fillable: set only by staff actions (temporary password) and cleared by a password change.
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * API tokens die with the credentials that earned them: a password change,
     * a deactivation or a deletion revokes every token of this user. One hook
     * covers every path (web admin, profile, portal access, API).
     */
    protected static function booted(): void
    {
        static::updated(function (User $user) {
            if ($user->wasChanged('password') || ($user->wasChanged('is_active') && ! $user->is_active)) {
                $user->tokens()->delete();
            }
        });

        // personal_access_tokens has no foreign key, so remove them explicitly.
        static::deleting(function (User $user) {
            $user->tokens()->delete();
        });
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /** Staff = any role other than Member (a pastor who is also a member counts as staff). */
    public function isStaff(): bool
    {
        return $this->getRoleNames()->contains(fn (string $role) => $role !== 'Member');
    }

    /** Issue a mobile app token named after the device: 30 days for staff, 90 for members. */
    public function createAppToken(string $deviceName): NewAccessToken
    {
        $days = $this->isStaff() ? self::STAFF_TOKEN_DAYS : self::MEMBER_TOKEN_DAYS;

        return $this->createToken($deviceName, ['*'], now()->addDays($days));
    }
}
