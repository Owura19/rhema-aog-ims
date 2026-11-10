<?php

namespace App\Support\Events;

use App\Models\Event;
use App\Models\EventRsvp;
use Illuminate\Support\Carbon;

/**
 * Whether a member can RSVP to an event, and why not. Used by the event
 * resource (what the app shows) and the RSVP endpoint (what it allows), so
 * the two can never disagree.
 */
final class RsvpWindow
{
    public const CANCELLED       = 'cancelled';
    public const ENDED           = 'ended';
    public const DEADLINE_PASSED = 'deadline_passed';
    public const FULL            = 'full';

    /** Reasons that stop ANY RSVP change (capacity is checked separately, per request). */
    public static function blockingReason(Event $event, ?Carbon $today = null): ?string
    {
        $today ??= today();

        if ($event->status === 'Cancelled') {
            return self::CANCELLED;
        }

        if (self::hasEnded($event, $today)) {
            return self::ENDED;
        }

        // The deadline day itself is still open.
        if ($event->rsvp_deadline && $today->gt($event->rsvp_deadline)) {
            return self::DEADLINE_PASSED;
        }

        return null;
    }

    public static function hasEnded(Event $event, ?Carbon $today = null): bool
    {
        $today ??= today();

        return $event->status === 'Completed' || ($event->end_date ?? $event->start_date)->lt($today);
    }

    /**
     * The "rsvp" block of an event in the app.
     *
     * @param int $confirmedGuests confirmed guests of everyone, this member included
     */
    public static function summary(Event $event, int $confirmedGuests, ?EventRsvp $mine): array
    {
        $myConfirmed = $mine && $mine->status === 'Confirmed';
        $reason = self::blockingReason($event);

        // Full only matters to someone without a confirmed place: they can still edit theirs.
        if ($reason === null && $event->capacity && ! $myConfirmed && $confirmedGuests >= $event->capacity) {
            $reason = self::FULL;
        }

        return [
            'required'      => (bool) $event->rsvp_required,
            'deadline'      => $event->rsvp_deadline?->toDateString(),
            'capacity'      => $event->capacity,
            'spots_left'    => $event->capacity ? max(0, $event->capacity - $confirmedGuests) : null,
            'open'          => $reason === null,
            'closed_reason' => $reason,
            'mine'          => $mine ? [
                'guests_count' => $mine->guests_count,
                'status'       => $mine->status,
                'created_at'   => $mine->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
