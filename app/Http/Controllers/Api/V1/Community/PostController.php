<?php

namespace App\Http\Controllers\Api\V1\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CommunityCommentResource;
use App\Http\Resources\V1\CommunityPostResource;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostPhoto;
use App\Support\Community\CommunityAccess;
use App\Support\Community\CommunityFeed;
use App\Support\Community\InvalidPhoto;
use App\Support\Community\PhotoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * GET    /community/posts?before_id=&limit=   newest first; meta.next_before_id
 * GET    /community/posts/{id}                post + its latest comments (oldest to newest)
 * POST   /community/posts                     multipart: body and/or photos[] (0-4)
 * DELETE /community/posts/{id}                own posts only
 *
 * Anything the viewer can't see (deleted, hidden, blocked either way, missing) is the
 * same 404; someone else's visible post is 403 for delete.
 */
class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:' . config('community.feed.max_limit')],
        ]);
        $limit = (int) ($data['limit'] ?? config('community.feed.default_limit'));
        $viewer = $request->user()->member_id;

        $rows = (new CommunityFeed($viewer))->posts()
            ->when($data['before_id'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();
        $page = $rows->take($limit);

        return response()->json([
            'data' => $page->map(fn (CommunityPost $post) => (new CommunityPostResource($post, $viewer))->resolve($request))->values(),
            'meta' => ['next_before_id' => $rows->count() > $limit ? $page->last()->id : null],
        ]);
    }

    public function show(Request $request, int $post): JsonResponse
    {
        $viewer = $request->user()->member_id;
        $feed = new CommunityFeed($viewer);
        $model = $feed->posts()->findOrFail($post);

        $limit = (int) config('community.feed.comments_on_post');
        $comments = $feed->comments($model)->orderByDesc('id')->limit($limit + 1)->get();
        $page = $comments->take($limit);

        return response()->json(['data' => (new CommunityPostResource($model, $viewer))->resolve($request) + [
            'comments' => [
                'data'           => $page->reverse()->map(fn (CommunityComment $c) => (new CommunityCommentResource($c, $viewer))->resolve($request))->values(),
                'next_before_id' => $comments->count() > $limit ? $page->last()->id : null,
            ],
        ]]);
    }

    public function store(Request $request, PhotoProcessor $processor): JsonResponse
    {
        $member = $request->user()->member;
        if ($denied = CommunityAccess::denial($member)) {
            return $denied;
        }

        $request->validate([
            'body'     => ['nullable', 'string', 'max:2000'],
            'photos'   => ['nullable', 'array', 'max:' . config('community.photos.max_per_post')],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:' . config('community.photos.max_kb')],
        ], [
            'photos.max'      => 'A post can have at most :max photos.',
            'photos.*.mimes'  => 'Photos must be JPEG, PNG or WebP images.',
            'photos.*.max'    => 'Each photo must be 8 MB or smaller.',
        ]);

        $body = trim((string) $request->input('body', ''));
        $files = array_values($request->file('photos', []));
        if ($body === '' && $files === []) {
            throw ValidationException::withMessages(['body' => 'Write something or add a photo.']);
        }

        $stored = [];
        try {
            foreach ($files as $index => $file) {
                try {
                    $stored[] = $processor->store($file);
                } catch (InvalidPhoto $e) {
                    throw ValidationException::withMessages(["photos.{$index}" => $e->getMessage()]);
                }
            }

            $post = DB::transaction(function () use ($member, $body, $stored) {
                $post = CommunityPost::create(['member_id' => $member->id, 'body' => $body !== '' ? $body : null]);
                foreach ($stored as $position => $photo) {
                    CommunityPostPhoto::create($photo + ['post_id' => $post->id, 'position' => $position]);
                }

                return $post;
            });
        } catch (Throwable $e) {
            foreach ($stored as $photo) {
                $processor->delete($photo['path'], $photo['thumb_path']);   // nothing left behind on failure
            }
            throw $e;
        }

        $post = (new CommunityFeed($member->id))->posts()->findOrFail($post->id);

        return (new CommunityPostResource($post, $member->id))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, int $post): Response
    {
        $viewer = $request->user()->member_id;
        $model = (new CommunityFeed($viewer))->posts()->findOrFail($post);
        abort_unless($model->member_id === $viewer, 403);

        $model->delete();   // soft delete; removed for good (with its photos) after community.purge_after_days

        return response()->noContent();
    }
}
