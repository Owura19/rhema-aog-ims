<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunityController extends Controller
{
    public function index()
    {
        $posts = Post::with(['user', 'likes', 'comments.user'])
            ->where('is_approved', true)
            ->orderBy('is_pinned', 'desc')
            ->latest()
            ->paginate(20);

        return view('community.index', compact('posts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content'    => 'nullable|string|max:5000',
            'type'       => 'required|in:text,photo,video,announcement',
            'media'      => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov|max:51200',
            'visibility' => 'required|in:everyone,members,leaders',
        ]);

        if (empty($validated['content']) && !$request->hasFile('media')) {
            return back()->with('error', 'Please add some text or media to your post.');
        }

        $mediaPath = null;
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaPath = $file->store('community/media', 'public');
            $validated['type'] = in_array($file->getClientOriginalExtension(), ['mp4', 'mov']) ? 'video' : 'photo';
        }

        Post::create([
            'user_id'    => auth()->id(),
            'content'    => $validated['content'] ?? null,
            'type'       => $validated['type'],
            'media_path' => $mediaPath,
            'visibility' => $validated['visibility'],
            'is_approved' => true,
        ]);

        return back()->with('success', 'Post shared successfully!');
    }

    public function destroy(Post $post)
    {
        if ($post->user_id !== auth()->id() && !auth()->user()->hasRole('Super Admin')) {
            return back()->with('error', 'You cannot delete this post.');
        }

        if ($post->media_path) {
            Storage::disk('public')->delete($post->media_path);
        }

        $post->delete();
        return back()->with('success', 'Post deleted.');
    }

    public function like(Post $post)
    {
        $existing = PostLike::where('post_id', $post->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('likes_count');
            $liked = false;
        } else {
            PostLike::create([
                'post_id' => $post->id,
                'user_id' => auth()->id(),
            ]);
            $post->increment('likes_count');
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => $post->fresh()->likes_count,
        ]);
    }

    public function comment(Request $request, Post $post)
    {
        $validated = $request->validate([
            'content'   => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:post_comments,id',
        ]);

        $comment = PostComment::create([
            'post_id'   => $post->id,
            'user_id'   => auth()->id(),
            'content'   => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        $post->increment('comments_count');

        return back()->with('success', 'Comment added!');
    }

    public function deleteComment(PostComment $comment)
    {
        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole('Super Admin')) {
            return back()->with('error', 'You cannot delete this comment.');
        }

        $comment->post->decrement('comments_count');
        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }

    public function pin(Post $post)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            return back()->with('error', 'Only admins can pin posts.');
        }

        $post->update(['is_pinned' => !$post->is_pinned]);
        return back()->with('success', $post->is_pinned ? 'Post pinned.' : 'Post unpinned.');
    }
}