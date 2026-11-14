<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sermon;
use App\Models\SermonSeries;
use Illuminate\Http\JsonResponse;

/**
 * GET /sermon-series
 *
 * Only series with at least one published sermon; the count and the latest date
 * cover published sermons only. Newest series activity first, then title.
 */
class SermonSeriesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $published = fn ($q) => $q->published();

        $series = SermonSeries::query()
            ->whereHas('sermons', $published)
            ->withCount(['sermons as sermon_count' => $published])
            ->withMax(['sermons as latest_preached_on' => $published], 'preached_on')
            ->orderByDesc('latest_preached_on')
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $series->map(fn (SermonSeries $item) => [
                'id'                 => $item->id,
                'title'              => $item->title,
                'description'        => $item->description,
                'sermon_count'       => (int) $item->sermon_count,
                'latest_preached_on' => substr((string) $item->latest_preached_on, 0, 10),
            ])->values(),
        ]);
    }
}
