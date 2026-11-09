<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CalendarItemResource;
use App\Support\Calendar\ChurchCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * GET /calendar?from=YYYY-MM-DD&to=YYYY-MM-DD
 * Defaults: from = today, to = from + 29 days. At most 92 days, inclusive. Not paginated.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = isset($data['from']) ? Carbon::createFromFormat('!Y-m-d', $data['from']) : today();
        $to   = isset($data['to']) ? Carbon::createFromFormat('!Y-m-d', $data['to']) : $from->copy()->addDays(ChurchCalendar::DEFAULT_DAYS - 1);

        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        if ((int) $from->diffInDays($to) + 1 > ChurchCalendar::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'The calendar can show at most ' . ChurchCalendar::MAX_DAYS . ' days at a time.']);
        }

        return CalendarItemResource::collection(ChurchCalendar::between($from, $to))
            ->additional(['meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]])
            ->response();
    }
}
