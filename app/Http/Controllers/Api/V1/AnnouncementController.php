<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

/**
 * GET /announcements -> {data:[{id, title, body, link_url, is_pinned, starts_at, ends_at}]}
 *
 * Live announcements only (published, started, not ended), pinned first then newest
 * start, at most 10. Any active login may read them (no member record needed).
 */
class AnnouncementController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $announcements = Announcement::query()->live()->appOrder()->limit(Announcement::API_LIMIT)->get();

        return response()->json([
            'data' => $announcements->map(fn (Announcement $a) => [
                'id'        => $a->id,
                'title'     => $a->title,
                'body'      => $a->body,
                'link_url'  => $a->link_url,
                'is_pinned' => $a->is_pinned,
                'starts_at' => $a->starts_at->toIso8601String(),
                'ends_at'   => $a->ends_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
