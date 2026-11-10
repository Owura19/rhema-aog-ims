<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DevotionResource;
use App\Models\Devotion;
use App\Support\Api\ApiError;
use App\Support\Devotions\DevotionLanguage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * GET /devotions/today?lang=
 * GET /devotions?before=YYYY-MM-DD&limit=&lang=   (archive, newest first)
 * GET /devotions/{date}?lang=
 *
 * Only LIVE devotions (approved, dated today or earlier). A draft, deleted, missing,
 * impossible or future date all get the same 404, so the app can never learn that a
 * future devotion exists. Staff without a member record may read devotions too.
 */
class DevotionController extends Controller
{
    public const DEFAULT_LIMIT = 10;
    public const MAX_LIMIT     = 30;

    public function today(Request $request): JsonResponse
    {
        $lang = $this->lang($request);

        $devotion = $this->live()->where('publish_date', today()->toDateString())->first();

        if (! $devotion) {
            return ApiError::response('no_devotion_today', 'There is no devotion for today yet.', 404);
        }

        return (new DevotionResource($devotion, $lang))->response();
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'before' => ['nullable', 'date_format:Y-m-d'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
            'lang'   => ['nullable', 'string', Rule::in(DevotionLanguage::supported())],
        ]);
        $lang = $data['lang'] ?? DevotionLanguage::default();
        $limit = (int) ($data['limit'] ?? self::DEFAULT_LIMIT);

        // One extra row tells us whether another page exists.
        $rows = $this->live()
            ->when(isset($data['before']), fn (Builder $q) => $q->where('publish_date', '<', $data['before']))
            ->orderByDesc('publish_date')
            ->limit($limit + 1)
            ->get();

        $page = $rows->take($limit);

        return response()->json([
            'data' => $page->map(fn (Devotion $devotion) => (new DevotionResource($devotion, $lang))->resolve($request))->values(),
            'meta' => [
                'next_before' => $rows->count() > $limit ? $page->last()->publish_date->toDateString() : null,
            ],
        ]);
    }

    public function show(Request $request, string $date): JsonResponse
    {
        $lang = $this->lang($request);

        $day = Carbon::createFromFormat('!Y-m-d', $date);
        abort_unless($day && $day->format('Y-m-d') === $date, 404);   // e.g. 2026-02-30

        $devotion = $this->live()->where('publish_date', $date)->first();
        abort_unless($devotion, 404);   // draft, deleted, missing or future: the same 404

        return (new DevotionResource($devotion, $lang))->response();
    }

    private function live(): Builder
    {
        return Devotion::query()->live()->with(['theme', 'translations']);
    }

    private function lang(Request $request): string
    {
        return $request->validate([
            'lang' => ['nullable', 'string', Rule::in(DevotionLanguage::supported())],
        ])['lang'] ?? DevotionLanguage::default();
    }
}
