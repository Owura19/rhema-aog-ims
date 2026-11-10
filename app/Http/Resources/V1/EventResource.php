<?php

namespace App\Http\Resources\V1;

use App\Support\Api\Money;
use App\Support\Events\RsvpWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An event as members see it. Built from App\Support\Events\MemberEvents::query(),
 * which loads `confirmed_guests` and, in `rsvps`, ONLY the signed-in member's RSVP.
 * Never who else has RSVP'd, and never created_by.
 *
 * @mixin \App\Models\Event
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'type'         => $this->type,
            'description'  => $this->description,
            'start_date'   => $this->start_date->toDateString(),
            'end_date'     => $this->end_date?->toDateString(),
            'start_time'   => self::time($this->start_time),
            'end_time'     => self::time($this->end_time),
            'venue'        => $this->venue,
            'address'      => $this->address,
            'banner_url'   => $this->banner_image ? asset('storage/' . $this->banner_image) : null,
            'is_free'      => (bool) $this->is_free,
            'ticket_price' => Money::format($this->ticket_price),
            'status'       => $this->status,
            'rsvp'         => RsvpWindow::summary(
                $this->resource,
                (int) $this->confirmed_guests,
                $this->rsvps->first(),
            ),
        ];
    }

    /** "09:00:00" → "09:00" */
    public static function time(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }
}
