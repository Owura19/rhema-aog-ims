<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CalendarItemResource;
use App\Support\Calendar\ChurchCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /programs/upcoming?limit= (default 5, 1–20) — the next items of the church calendar, for the home screen. */
class UpcomingProgramsController extends Controller
{
    public const DEFAULT_LIMIT = 5;
    public const MAX_LIMIT     = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ]);

        return CalendarItemResource::collection(ChurchCalendar::upcoming((int) ($data['limit'] ?? self::DEFAULT_LIMIT)))
            ->response();
    }
}
