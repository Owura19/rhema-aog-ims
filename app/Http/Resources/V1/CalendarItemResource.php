<?php

namespace App\Http\Resources\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One calendar item: an Event or a ChurchService.
 *
 * Every item has the same 11 keys. Services expose ONLY: id, name (as title),
 * service_type (as type), service_date (as date), start_time, end_time, venue,
 * status. Never description, biometric_enabled, created_by, timestamps or
 * attendance figures. Full event details (RSVP etc.) come from /events/{id}.
 *
 * @mixin \App\Models\Event|\App\Models\ChurchService
 */
class CalendarItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        if ($item instanceof Event) {
            return [
                'kind'       => 'event',
                'id'         => $item->id,
                'title'      => $item->title,
                'type'       => $item->type,
                'date'       => $item->start_date->toDateString(),
                'end_date'   => $item->end_date?->toDateString(),
                'start_time' => EventResource::time($item->start_time),
                'end_time'   => EventResource::time($item->end_time),
                'venue'      => $item->venue,
                'status'     => $item->status,
                'cancelled'  => $item->status === 'Cancelled',
            ];
        }

        return [
            'kind'       => 'service',
            'id'         => $item->id,
            'title'      => $item->name,
            'type'       => $item->service_type,
            'date'       => $item->service_date->toDateString(),
            'end_date'   => null,
            'start_time' => EventResource::time($item->start_time),
            'end_time'   => EventResource::time($item->end_time),
            'venue'      => $item->venue,
            'status'     => $item->status,
            'cancelled'  => $item->status === 'Cancelled',
        ];
    }
}
