<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventResource;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Support\Api\ApiError;
use App\Support\Events\MemberEvents;
use App\Support\Events\RsvpWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * POST   /events/{id}/rsvp {guests_count?: 1–10 (default 1, the member included), notes?}
 * DELETE /events/{id}/rsvp
 *
 * The member is always the signed-in member; any member_id in the request is ignored.
 * One row per member per event (unique event_id + member_id): POST creates it (201)
 * or updates / reactivates it (200); DELETE marks it Cancelled (history kept), 204.
 * The event row is locked while counting, so concurrent RSVPs can't overbook.
 */
class EventRsvpController extends Controller
{
    public const MAX_PARTY = 10;

    private const MESSAGES = [
        RsvpWindow::CANCELLED       => ['event_cancelled', 'This event has been cancelled.'],
        RsvpWindow::ENDED           => ['event_ended', 'This event has already ended.'],
        RsvpWindow::DEADLINE_PASSED => ['rsvp_closed', 'RSVPs for this event are closed.'],
    ];

    public function store(Request $request, int $event): JsonResponse
    {
        $data = $request->validate([
            'guests_count' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_PARTY],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $memberId = $request->user()->member->id;
        $party = (int) ($data['guests_count'] ?? 1);

        $result = DB::transaction(function () use ($event, $memberId, $party, $data) {
            $model = $this->lockedVisibleEvent($event);

            if ($reason = RsvpWindow::blockingReason($model)) {
                return $this->refusal($reason);
            }

            $mine = EventRsvp::where('event_id', $model->id)->where('member_id', $memberId)->first();
            $confirmed = (int) EventRsvp::where('event_id', $model->id)->where('status', 'Confirmed')->sum('guests_count');
            $othersConfirmed = $confirmed - ($mine?->status === 'Confirmed' ? $mine->guests_count : 0);

            if ($model->capacity && $othersConfirmed + $party > $model->capacity) {
                return ApiError::response('event_full', 'Sorry, there are not enough places left.', 409, null, [], [
                    'spots_left' => max(0, $model->capacity - $othersConfirmed),
                ]);
            }

            $values = ['guests_count' => $party, 'status' => 'Confirmed'];
            if (array_key_exists('notes', $data)) {
                $values['notes'] = $data['notes'];
            }

            if ($mine) {
                $mine->update($values);

                return 200;
            }

            EventRsvp::create(['event_id' => $model->id, 'member_id' => $memberId] + $values);

            return 201;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $fresh = MemberEvents::query($memberId)->findOrFail($event);

        return (new EventResource($fresh))->response()->setStatusCode($result);
    }

    public function destroy(Request $request, int $event): Response|JsonResponse
    {
        $memberId = $request->user()->member->id;

        return DB::transaction(function () use ($event, $memberId) {
            $model = $this->lockedVisibleEvent($event);

            // Attendance history isn't rewritten after the event; cancelling before then is always allowed.
            if (RsvpWindow::hasEnded($model)) {
                return $this->refusal(RsvpWindow::ENDED);
            }

            EventRsvp::where('event_id', $model->id)
                ->where('member_id', $memberId)
                ->where('status', '!=', 'Cancelled')
                ->update(['status' => 'Cancelled']);

            return response()->noContent();   // also when there was nothing to cancel
        });
    }

    /** Draft and deleted events are invisible: 404 (ModelNotFoundException). */
    private function lockedVisibleEvent(int $id): Event
    {
        return Event::query()->whereIn('status', MemberEvents::VISIBLE_STATUSES)->lockForUpdate()->findOrFail($id);
    }

    private function refusal(string $reason): JsonResponse
    {
        [$code, $message] = self::MESSAGES[$reason];

        return ApiError::response($code, $message, 409);
    }
}
