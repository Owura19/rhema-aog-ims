<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DayBornWeekResource;
use App\Models\DayBornWeek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /day-born/latest      -> {data: Week|null}  the most recent PUBLISHED week (null: none yet)
 * GET /day-born?page=&per_page= -> paginated published weeks, newest first
 *
 * Group totals only (never per member). Any active login may read them (no member needed).
 */
class DayBornController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $week = DayBornWeek::published()->with('offerings')->orderByDesc('service_date')->first();

        return response()->json(['data' => $week ? (new DayBornWeekResource($week))->resolve($request) : null]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $weeks = DayBornWeek::published()
            ->with('offerings')
            ->orderByDesc('service_date')
            ->paginate((int) ($data['per_page'] ?? 10))
            ->withQueryString();

        return DayBornWeekResource::collection($weeks)->response();
    }
}
