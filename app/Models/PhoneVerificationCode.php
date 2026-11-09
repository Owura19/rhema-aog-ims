<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * An SMS one-time code (activation or password reset). Codes and session tokens
 * are stored only as HMAC hashes — see App\Services\Auth\VerificationCodes.
 * Rows older than a day are pruned (scheduled model:prune).
 */
class PhoneVerificationCode extends Model
{
    use Prunable;

    public const PURPOSE_ACTIVATION     = 'activation';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'phone',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'session_token_hash',
        'session_expires_at',
        'consumed_at',
        'request_ip',
    ];

    protected $hidden = [
        'code_hash',
        'session_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'attempts'           => 'integer',
            'expires_at'         => 'datetime',
            'verified_at'        => 'datetime',
            'session_expires_at' => 'datetime',
            'consumed_at'        => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }
}
