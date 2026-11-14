<?php

namespace App\Http\Controllers\Api\V1\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityMemberBlock;
use App\Models\Member;
use App\Support\Community\Author;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * GET    /community/blocks              the members I blocked
 * POST   /community/blocks/{member_id}  201 (200 if already blocked)
 * DELETE /community/blocks/{member_id}  204 (safe to repeat)
 * A block hides both members' posts and comments from each other. Any member may block.
 */
class BlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $blocks = CommunityMemberBlock::with('blocked')
            ->where('blocker_member_id', $request->user()->member_id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $blocks->map(fn (CommunityMemberBlock $block) => [
            'member_id'    => $block->blocked_member_id,
            'display_name' => Author::of($block->blocked)['display_name'],
            'blocked_at'   => $block->created_at?->toIso8601String(),
        ])->values()]);
    }

    public function store(Request $request, int $member): JsonResponse
    {
        $me = $request->user()->member_id;
        if ($member === $me) {
            throw ValidationException::withMessages(['member_id' => "You can't block yourself."]);
        }
        abort_unless(Member::whereKey($member)->exists(), 404);

        $block = CommunityMemberBlock::firstOrCreate(['blocker_member_id' => $me, 'blocked_member_id' => $member]);

        return response()->json(['data' => [
            'member_id'  => $member,
            'blocked_at' => $block->created_at?->toIso8601String(),
        ]], $block->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $member): Response
    {
        CommunityMemberBlock::where('blocker_member_id', $request->user()->member_id)->where('blocked_member_id', $member)->delete();

        return response()->noContent();
    }
}
