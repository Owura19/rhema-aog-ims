<?php

namespace App\Support\Calendar;

use App\Models\ChurchService;
use App\Models\Event;
use App\Support\Events\MemberEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One date-sorted feed of church events and services for the app's calendar and
 * "upcoming programmes". Items are Event and ChurchService models, shaped by
 * App\Http\Resources\V1\CalendarItemResource.
 *
 *  - Events: Draft and soft-deleted are never included (MemberEvents::VISIBLE_STATUSES).
 *  - Services: every status (Scheduled, Ongoing, Completed, Cancelled) — none means hidden.
 *  - Cancelled items stay in, marked.
 *  - Order: date, start_time (none first), services before events, id.
 */
final class ChurchCalendar
{
    public const DEFAULT_DAYS = 30;   // from today: today + 29
    public const MAX_DAYS     = 92;   // inclusive

    /** @return Collection<int, Event|ChurchService> items whose dates overlap [$from, $to] */
    public static function between(Carbon $from, Carbon $to): Collection
    {
        $events = Event::query()
            ->whereIn('status', MemberEvents::VISIBLE_STATUSES)
            ->where('start_date', '<=', $to->toDateString())
            ->whereRaw(MemberEvents::LAST_DAY . ' >= ?', [$from->toDateString()])
            ->get();

        $services = ChurchService::query()
            ->whereBetween('service_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return self::sorted($events->concat($services));
    }

    /**
     * The next $limit items from today: events that haven't ended (as on /events?scope=upcoming)
     * and services dated today or later that aren't Completed.
     *
     * @return Collection<int, Event|ChurchService>
     */
    public static function upcoming(int $limit): Collection
    {
        $today = today()->toDateString();

        // Each list is fetched in feed order, so the first $limit of each is enough to merge.
        $events = Event::query()
            ->whereIn('status', MemberEvents::UPCOMING_STATUSES)
            ->whereRaw(MemberEvents::LAST_DAY . ' >= ?', [$today])
            ->orderBy('start_date')
            ->orderBy('start_time')   // MySQL sorts NULL first
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $services = ChurchService::query()
            ->where('service_date', '>=', $today)
            ->where('status', '!=', 'Completed')
            ->orderBy('service_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return self::sorted($events->concat($services))->take($limit)->values();
    }

    /** @param Collection<int, Event|ChurchService> $items */
    private static function sorted(Collection $items): Collection
    {
        return $items->sort(fn ($a, $b) => self::sortKey($a) <=> self::sortKey($b))->values();
    }

    /** [date, start time ('' = none, sorts first), services before events, id] */
    private static function sortKey(Event|ChurchService $item): array
    {
        return $item instanceof Event
            ? [$item->start_date->toDateString(), (string) $item->start_time, 1, $item->id]
            : [$item->service_date->toDateString(), (string) $item->start_time, 0, $item->id];
    }
}
