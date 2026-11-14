<?php

namespace App\Http\Controllers\Api\V1\Community;

use App\Events\Community\CommunityPostCommented;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CommunityCommentResource;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Support\Community\CommunityAccess;
use App\Support\Community\CommunityFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GET    /community/posts/{id}/comments?before_id=&limit=  older comments; each page oldest to newest
 * POST   /community/posts/{id}/comments  {body}
 * DELETE /community/comments/{id}        own comments only
 */
class CommentController extends Controller
{
    public function index(Request $request, int $post): JsonResponse
    {
        $data = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:' . config('community.feed.max_limit')],
        ]);
        $limit = (int) ($data['limit'] ?? config('community.feed.comments_on_post'));
        $viewer = $request->user()->member_id;
        $feed = new CommunityFeed($viewer);
        $model = $feed->posts()->findOrFail($post);

        $rows = $feed->comments($model)
            ->when($data['before_id'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();
        $page = $rows->take($limit);

        return response()->json([
            'data' => $page->reverse()->map(fn (CommunityComment $c) => (new CommunityCommentResource($c, $viewer))->resolve($request))->values(),
            'meta' => ['next_before_id' => $rows->count() > $limit ? $page->last()->id : null],
        ]);
    }

    public function store(Request $request, int $post): JsonResponse
    {
        $member = $request->user()->member;
        $model = CommunityFeed::for($member)->posts()->findOrFail($post);
        if ($denied = CommunityAccess::denial($member)) {
            return $denied;
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write a comment first.']);
        }

        $comment = DB::transaction(function () use ($model, $member, $body) {
            $comment = CommunityComment::create(['post_id' => $model->id, 'member_id' => $member->id, 'body' => $body]);
            CommunityPost::whereKey($model->id)->increment('comment_count');

            return $comment;
        });

        CommunityPostCommented::dispatch($comment);

        return (new CommunityCommentResource($comment->load('author'), $member->id))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, int $comment): Response
    {
        $viewer = $request->user()->member_id;
        $model = (new CommunityFeed($viewer))->comment($comment);
        abort_unless($model, 404);
        abort_unless($model->member_id === $viewer, 403);

        DB::transaction(function () use ($model) {
            $model->delete();
            CommunityPost::withTrashed()->whereKey($model->post_id)->where('comment_count', '>', 0)->decrement('comment_count');
        });

        return response()->noContent();
    }
}
