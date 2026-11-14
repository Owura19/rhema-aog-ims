<?php

namespace App\Http\Controllers\Api\V1\Community;

use App\Events\Community\CommunityPostLiked;
use App\Http\Controllers\Controller;
use App\Models\CommunityLike;
use App\Models\CommunityPost;
use App\Support\Community\CommunityAccess;
use App\Support\Community\CommunityFeed;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST   /community/posts/{id}/like   -> {data:{liked:true, like_count}}  (safe to repeat)
 * DELETE /community/posts/{id}/like   -> {data:{liked:false, like_count}} (safe to repeat)
 * Liking needs the same access as posting; un-liking is always allowed.
 */
class LikeController extends Controller
{
    public function store(Request $request, int $post): JsonResponse
    {
        $member = $request->user()->member;
        $model = CommunityFeed::for($member)->posts()->findOrFail($post);
        if ($denied = CommunityAccess::denial($member)) {
            return $denied;
        }

        try {
            $like = DB::transaction(function () use ($model, $member) {
                $like = CommunityLike::create(['post_id' => $model->id, 'member_id' => $member->id]);
                CommunityPost::whereKey($model->id)->increment('like_count');

                return $like;
            });
            CommunityPostLiked::dispatch($like);
        } catch (UniqueConstraintViolationException) {
            // already liked: nothing changes
        }

        return $this->state($model->id, true);
    }

    public function destroy(Request $request, int $post): JsonResponse
    {
        $member = $request->user()->member;
        $model = CommunityFeed::for($member)->posts()->findOrFail($post);

        DB::transaction(function () use ($model, $member) {
            $deleted = CommunityLike::where('post_id', $model->id)->where('member_id', $member->id)->delete();
            if ($deleted > 0) {
                CommunityPost::whereKey($model->id)->where('like_count', '>', 0)->decrement('like_count');
            }
        });

        return $this->state($model->id, false);
    }

    private function state(int $postId, bool $liked): JsonResponse
    {
        return response()->json(['data' => [
            'liked'      => $liked,
            'like_count' => (int) CommunityPost::whereKey($postId)->value('like_count'),
        ]]);
    }
}
