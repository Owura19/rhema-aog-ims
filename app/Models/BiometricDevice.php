<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class BiometricDevice extends Model
{
    use HasFactory;

    /** Fields staff may edit. The connection key and the "last seen / last punch" times are never mass-assigned. */
    protected $fillable = [
        'name',
        'serial_number',
        'ip_address',
        'port',
        'location',
        'is_active',
        'last_synced_at',
        'notes',
    ];

    /** Never serialised (the hash is not a secret like the key, but nothing needs it). */
    protected $hidden = ['api_token_hash'];

    protected $casts = [
        'is_active'        => 'boolean',
        'last_synced_at'   => 'datetime',
        'last_seen_at'     => 'datetime',
        'last_punch_at'    => 'datetime',
        'token_created_at' => 'datetime',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function punches()
    {
        return $this->hasMany(BiometricPunch::class);
    }

    // ── CONNECTION KEY (for the bridge program) ────────────

    /** The stored form of a key: sha256, hex. The key itself is never stored. */
    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * The ACTIVE device this key belongs to, or null. The hash lookup is compared again with
     * hash_equals so the decision never rests on the database's string comparison alone.
     */
    public static function findByKey(string $key): ?self
    {
        if ($key === '') {
            return null;
        }

        $hash   = self::hashKey($key);
        $device = static::where('api_token_hash', $hash)->where('is_active', true)->first();

        return $device && hash_equals((string) $device->api_token_hash, $hash) ? $device : null;
    }

    /**
     * Makes a new connection key and returns it. This is the only time it exists in plain text:
     * only its hash is saved, and any earlier key stops working at once.
     */
    public function generateConnectionKey(): string
    {
        $key = Str::random(48);

        $this->forceFill(['api_token_hash' => self::hashKey($key), 'token_created_at' => now()])->save();

        return $key;
    }

    public function revokeConnectionKey(): void
    {
        $this->forceFill(['api_token_hash' => null, 'token_created_at' => null])->save();
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    public function getLastSyncedLabelAttribute(): string
    {
        return $this->last_synced_at
            ? $this->last_synced_at->diffForHumans()
            : 'Never synced';
    }

    /** Can the server dial this device directly (Test connection / Sync from Device)? */
    public function getHasIpAttribute(): bool
    {
        return filled($this->ip_address) && filled($this->port);
    }

    public function getHasKeyAttribute(): bool
    {
        return filled($this->api_token_hash);
    }
}
