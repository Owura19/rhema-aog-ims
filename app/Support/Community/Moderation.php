<?php

namespace App\Support\Community;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hiding and unhiding posts and comments, and closing their reports.
 * Hidden items disappear for everyone but their author; nothing is deleted.
 */
final class Moderation
{
    public const AUTO_HIDE_REASON = 'Hidden automatically after several reports.';

    /** Hides the item. By a moderator, its open reports become "actioned". */
    public static function hide(CommunityPost|CommunityComment $item, ?User $moderator, string $reason, bool $automatic = false): void
    {
        DB::transaction(function () use ($item, $moderator, $reason, $automatic) {
            $item->forceFill([
                'hidden_at'            => now(),
                'hidden_by'            => $moderator?->id,
                'hide_reason'          => mb_substr($reason, 0, 255),
                'hidden_automatically' => $automatic,
            ])->save();

            if ($moderator) {
                self::closeReports($item, CommunityReport::STATUS_ACTIONED, $moderator);
            }
        });
    }

    /** Shows the item again; its open reports are dismissed (the moderator found it fine). */
    public static function unhide(CommunityPost|CommunityComment $item, User $moderator): void
    {
        DB::transaction(function () use ($item, $moderator) {
            $item->forceFill(['hidden_at' => null, 'hidden_by' => null, 'hide_reason' => null, 'hidden_automatically' => false])->save();
            self::closeReports($item, CommunityReport::STATUS_DISMISSED, $moderator);
        });
    }

    /** @return int the number of reports closed */
    public static function closeReports(CommunityPost|CommunityComment $item, string $status, User $moderator): int
    {
        return CommunityReport::open()
            ->for(self::type($item), $item->id)
            ->update(['status' => $status, 'reviewed_by' => $moderator->id, 'reviewed_at' => now(), 'updated_at' => now()]);
    }

    public static function type(CommunityPost|CommunityComment $item): string
    {
        return $item instanceof CommunityPost ? CommunityReport::TARGET_POST : CommunityReport::TARGET_COMMENT;
    }
}
