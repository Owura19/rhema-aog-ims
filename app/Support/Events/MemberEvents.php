<?php

namespace App\Support\Events;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;

/**
 * Events as members see them in the app.
 *  - Draft (and soft-deleted) events are invisible: they get 404, never 403.
 *  - An event has "ended" when its last day (end_date, or start_date if none) is before today.
 *  - Each query carries the confirmed guest total and the signed-in member's own RSVP,
 *    so lists never run a query per event.
 */
final class MemberEvents
{
    public const VISIBLE_STATUSES  = ['Published', 'Ongoing', 'Completed', 'Cancelled'];
    public const UPCOMING_STATUSES = ['Published', 'Ongoing', 'Cancelled'];   // cancelled stay listed, marked
    public const RECENT_STATUSES   = ['Published', 'Ongoing', 'Completed'];
    public const RECENT_DAYS       = 30;

    /** SQL for an event's last day. */
    public const LAST_DAY = 'COALESCE(end_date, start_date)';

    /**
     * Visible events with:
     *  - confirmed_guests: SUM(guests_count) of Confirmed RSVPs (null when none)
     *  - rsvps: ONLY this member's RSVP (0 or 1 row; unique event_id + member_id)
     */
    public static function query(int $memberId): Builder
    {
        return Event::query()
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->withSum(['rsvps as confirmed_guests' => fn ($q) => $q->where('status', 'Confirmed')], 'guests_count')
            ->with(['rsvps' => fn ($q) => $q->where('member_id', $memberId)]);
    }

    /** Not ended yet; earliest first. Cancelled ones included (marked by status). */
    public static function upcoming(int $memberId): Builder
    {
        return self::query($memberId)
            ->whereIn('status', self::UPCOMING_STATUSES)
            ->whereRaw(self::LAST_DAY . ' >= ?', [today()->toDateString()])
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    /** Ended (or marked Completed) within the last 30 days; most recent first. Cancelled excluded. */
    public static function recent(int $memberId): Builder
    {
        return self::query($memberId)
            ->whereIn('status', self::RECENT_STATUSES)
            ->whereRaw(self::LAST_DAY . ' >= ?', [today()->subDays(self::RECENT_DAYS)->toDateString()])
            ->where(fn ($q) => $q
                ->whereRaw(self::LAST_DAY . ' < ?', [today()->toDateString()])
                ->orWhere('status', 'Completed'))
            ->orderByRaw(self::LAST_DAY . ' DESC')
            ->orderByDesc('id');
    }
}
