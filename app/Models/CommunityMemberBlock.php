<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One member blocking another. Blocks hide content in both directions. */
class CommunityMemberBlock extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['blocker_member_id', 'blocked_member_id'];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'blocker_member_id')->withTrashed();
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'blocked_member_id')->withTrashed();
    }

    /** @return list<int> members hidden from $memberId: those they blocked and those who blocked them */
    public static function hiddenFrom(int $memberId): array
    {
        $blocked = static::where('blocker_member_id', $memberId)->pluck('blocked_member_id');
        $blockers = static::where('blocked_member_id', $memberId)->pluck('blocker_member_id');

        return $blocked->merge($blockers)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
