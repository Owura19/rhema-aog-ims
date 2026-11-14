<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SermonResource;
use App\Models\Sermon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /sermons?series=&q=&page=&per_page=   (newest preached first)
 * GET /sermons/{id}
 *
 * Published sermons only. A draft, scheduled, deleted or missing sermon is the
 * same 404. Any active login may read sermons (no member record needed).
 */
class SermonController extends Controller
{
    public const DEFAULT_PER_PAGE = 15;
    public const MAX_PER_PAGE     = 30;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'series'   => ['nullable', 'integer', 'min:1'],
            'q'        => ['nullable', 'string', 'min:2', 'max:100'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ]);

        $sermons = Sermon::query()
            ->published()
            ->with('series')
            ->when($data['series'] ?? null, fn (Builder $q, $series) => $q->where('series_id', $series))
            ->when(isset($data['q']) ? trim($data['q']) : null, function (Builder $q, string $term) {
                $like = '%' . addcslashes($term, '\\%_') . '%';
                $q->where(fn (Builder $w) => $w
                    ->where('title', 'like', $like)
                    ->orWhere('preacher_name', 'like', $like)
                    ->orWhere('scripture_reference', 'like', $like));
            })
            ->orderByDesc('preached_on')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? self::DEFAULT_PER_PAGE))
            ->withQueryString();

        return SermonResource::collection($sermons)->response();
    }

    public function show(int $sermon): JsonResponse
    {
        $model = Sermon::query()->published()->with('series')->findOrFail($sermon);

        return (new SermonResource($model))->response();
    }
}
