<?php

namespace App\Http\Controllers\Api\V1\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Support\Community\CommunityFeed;
use App\Support\Community\Moderation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * POST /community/reports  {type: post|comment, id, reason, details?}
 *  -> 201 {data:{id, status:"received"}}; the same member reporting the same item again -> 200, same body.
 * Any member may report what they can see (minors and banned members too). Reporting your
 * own item is 422. When community.auto_hide_reports different members have open reports on
 * an item, it is hidden automatically until a moderator looks at it.
 */
class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'    => ['required', Rule::in([CommunityReport::TARGET_POST, CommunityReport::TARGET_COMMENT])],
            'id'      => ['required', 'integer', 'min:1'],
            'reason'  => ['required', Rule::in(array_keys(CommunityReport::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        $member = $request->user()->member;
        $feed = CommunityFeed::for($member);
        $target = $data['type'] === CommunityReport::TARGET_POST
            ? $feed->posts()->find($data['id'])
            : $feed->comment((int) $data['id']);
        abort_unless($target, 404);

        if ($target->member_id === $member->id) {
            throw ValidationException::withMessages(['id' => "You can't report your own {$data['type']}."]);
        }

        $existing = CommunityReport::where('reporter_member_id', $member->id)->for($data['type'], $target->id)->first();
        if ($existing) {
            return $this->received($existing, 200);
        }

        try {
            $report = DB::transaction(function () use ($member, $data, $target) {
                $report = CommunityReport::create([
                    'reporter_member_id' => $member->id,
                    'target_type'        => $data['type'],
                    'target_id'          => $target->id,
                    'reason'             => $data['reason'],
                    'details'            => isset($data['details']) ? trim($data['details']) ?: null : null,
                ]);
                $target->newQuery()->withTrashed()->whereKey($target->id)->increment('report_count');
                $this->autoHide($target);

                return $report;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->received(CommunityReport::where('reporter_member_id', $member->id)->for($data['type'], $target->id)->firstOrFail(), 200);
        }

        return $this->received($report, 201);
    }

    private function autoHide(CommunityPost|CommunityComment $target): void
    {
        $fresh = $target->newQuery()->withTrashed()->find($target->id);
        if (! $fresh || $fresh->isHidden()) {
            return;
        }

        $reporters = CommunityReport::open()->for(Moderation::type($fresh), $fresh->id)->distinct()->count('reporter_member_id');
        if ($reporters >= (int) config('community.auto_hide_reports')) {
            Moderation::hide($fresh, null, Moderation::AUTO_HIDE_REASON, automatic: true);
        }
    }

    private function received(CommunityReport $report, int $status): JsonResponse
    {
        return response()->json(['data' => ['id' => $report->id, 'status' => 'received']], $status);
    }
}
