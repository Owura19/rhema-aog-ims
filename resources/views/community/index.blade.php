@extends('layouts.app')

@section('title', 'Community')

@section('content')

<div style="max-width:680px; margin:0 auto;">

    <!-- Create Post -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-body">
            <form method="POST" action="{{ route('community.store') }}" enctype="multipart/form-data">
                @csrf
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <div class="member-avatar-placeholder" style="width:44px; height:44px; font-size:16px; flex-shrink:0;">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div style="flex:1;">
                        <textarea name="content" rows="3" class="form-control" placeholder="Share something with the Rhema family..." style="resize:none; border:none; padding:0; font-size:15px; box-shadow:none;">{{ old('content') }}</textarea>

                        <!-- Media Preview -->
                        <div id="media-preview" style="display:none; margin-top:12px;">
                            <img id="preview-img" style="max-width:100%; border-radius:8px; display:none;">
                            <video id="preview-video" style="max-width:100%; border-radius:8px; display:none;" controls></video>
                        </div>

                        <div style="border-top:1px solid #f1f5f9; margin-top:12px; padding-top:12px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; gap:8px; align-items:center;">
                                <label style="cursor:pointer; display:flex; align-items:center; gap:6px; color:#64748b; font-size:13px; font-weight:600; padding:6px 12px; border-radius:8px; background:#f1f5f9;">
                                    <i class="fas fa-image" style="color:#2563eb;"></i> Photo/Video
                                    <input type="file" name="media" accept="image/*,video/mp4,video/quicktime" style="display:none;" onchange="previewMedia(this)">
                                </label>
                                <select name="visibility" class="form-control" style="width:130px; font-size:13px; padding:6px 10px;">
                                    <option value="everyone">Everyone</option>
                                    <option value="members">Members Only</option>
                                    <option value="leaders">Leaders Only</option>
                                </select>
                                <input type="hidden" name="type" value="text">
                            </div>
                            <button type="submit" class="btn-primary" style="padding:8px 20px;">
                                <i class="fas fa-paper-plane"></i> Post
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Posts Feed -->
    @forelse($posts as $post)
    <div class="card" style="margin-bottom:20px;" id="post-{{ $post->id }}">
        <div class="card-body">

            <!-- Post Header -->
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="member-avatar-placeholder" style="width:40px; height:40px; font-size:15px;">
                        {{ strtoupper(substr($post->user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-weight:700; font-size:14px; color:#1e293b;">{{ $post->user->name }}</div>
                        <div style="font-size:12px; color:#94a3b8;">
                            {{ $post->time_ago }}
                            @if($post->is_pinned)
                                · <i class="fas fa-thumbtack" style="color:#e8a020;"></i> Pinned
                            @endif
                            @if($post->visibility !== 'everyone')
                                · <i class="fas fa-lock"></i> {{ ucfirst($post->visibility) }}
                            @endif
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:6px;">
                    @if(auth()->user()->hasRole('Super Admin'))
                        <form method="POST" action="{{ route('community.pin', $post) }}">
                            @csrf
                            <button type="submit" class="btn-outline btn-sm" title="{{ $post->is_pinned ? 'Unpin' : 'Pin' }}">
                                <i class="fas fa-thumbtack" style="{{ $post->is_pinned ? 'color:#e8a020;' : '' }}"></i>
                            </button>
                        </form>
                    @endif
                    @if($post->user_id === auth()->id() || auth()->user()->hasRole('Super Admin'))
                        <form method="POST" action="{{ route('community.destroy', $post) }}" onsubmit="return confirm('Delete this post?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Post Content -->
            @if($post->content)
                <p style="font-size:15px; color:#1e293b; line-height:1.7; margin:0 0 12px;">{{ $post->content }}</p>
            @endif

            <!-- Media -->
            @if($post->media_path)
                @if($post->type === 'video')
                    <video src="{{ asset('storage/'.$post->media_path) }}" controls style="width:100%; border-radius:8px; margin-bottom:12px; max-height:400px; object-fit:cover;"></video>
                @else
                    <img src="{{ asset('storage/'.$post->media_path) }}" style="width:100%; border-radius:8px; margin-bottom:12px; max-height:400px; object-fit:cover;">
                @endif
            @endif

            <!-- Like & Comment Bar -->
            <div style="display:flex; align-items:center; gap:16px; padding:10px 0; border-top:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9; margin-bottom:12px;">
                <button onclick="likePost({{ $post->id }}, this)" style="background:none; border:none; cursor:pointer; display:flex; align-items:center; gap:6px; font-size:14px; font-weight:600; color:{{ $post->isLikedBy(auth()->user()) ? '#2563eb' : '#64748b' }};" id="like-btn-{{ $post->id }}">
                    <i class="fas fa-heart" style="color:{{ $post->isLikedBy(auth()->user()) ? '#2563eb' : '#94a3b8' }};"></i>
                    <span id="likes-count-{{ $post->id }}">{{ $post->likes_count }}</span>
                    {{ $post->likes_count === 1 ? 'Like' : 'Likes' }}
                </button>
                <button onclick="toggleComments({{ $post->id }})" style="background:none; border:none; cursor:pointer; display:flex; align-items:center; gap:6px; font-size:14px; font-weight:600; color:#64748b;">
                    <i class="fas fa-comment" style="color:#94a3b8;"></i>
                    {{ $post->comments_count }} {{ $post->comments_count === 1 ? 'Comment' : 'Comments' }}
                </button>
            </div>

            <!-- Comments Section -->
            <div id="comments-{{ $post->id }}" style="display:none;">

                <!-- Existing Comments -->
                @foreach($post->comments as $comment)
                <div style="display:flex; gap:8px; margin-bottom:12px;">
                    <div class="member-avatar-placeholder" style="width:32px; height:32px; font-size:12px; flex-shrink:0;">
                        {{ strtoupper(substr($comment->user->name, 0, 1)) }}
                    </div>
                    <div style="flex:1;">
                        <div style="background:#f8fafc; border-radius:12px; padding:10px 14px;">
                            <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:2px;">{{ $comment->user->name }}</div>
                            <div style="font-size:14px; color:#374151;">{{ $comment->content }}</div>
                        </div>
                        <div style="display:flex; gap:12px; margin-top:4px; padding-left:4px;">
                            <span style="font-size:11px; color:#94a3b8;">{{ $comment->created_at->diffForHumans() }}</span>
                            @if($comment->user_id === auth()->id() || auth()->user()->hasRole('Super Admin'))
                                <form method="POST" action="{{ route('community.delete-comment', $comment) }}" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:none; border:none; cursor:pointer; font-size:11px; color:#dc2626;">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Add Comment -->
                <form method="POST" action="{{ route('community.comment', $post) }}" style="display:flex; gap:8px; margin-top:8px;">
                    @csrf
                    <div class="member-avatar-placeholder" style="width:32px; height:32px; font-size:12px; flex-shrink:0;">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div style="flex:1; display:flex; gap:8px;">
                        <input type="text" name="content" placeholder="Write a comment..." required class="form-control" style="font-size:13px; border-radius:20px; padding:8px 16px;">
                        <button type="submit" class="btn-primary btn-sm" style="border-radius:20px; white-space:nowrap;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>

            </div>

        </div>
    </div>
    @empty
    <div class="card">
        <div style="text-align:center; padding:60px; color:#94a3b8;">
            <i class="fas fa-users" style="font-size:48px; display:block; margin-bottom:16px;"></i>
            <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No posts yet</div>
            <div style="font-size:13px;">Be the first to share something with the Rhema family!</div>
        </div>
    </div>
    @endforelse

    <!-- Pagination -->
    @if($posts->hasPages())
    <div style="margin-top:20px;">
        {{ $posts->links() }}
    </div>
    @endif

</div>

<script>
function toggleComments(postId) {
    const section = document.getElementById('comments-' + postId);
    section.style.display = section.style.display === 'none' ? 'block' : 'none';
}

function likePost(postId, btn) {
    fetch('/community/' + postId + '/like', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('likes-count-' + postId).textContent = data.likes_count;
        const icon = btn.querySelector('i');
        if (data.liked) {
            btn.style.color = '#2563eb';
            icon.style.color = '#2563eb';
        } else {
            btn.style.color = '#64748b';
            icon.style.color = '#94a3b8';
        }
    });
}

function previewMedia(input) {
    const preview = document.getElementById('media-preview');
    const img = document.getElementById('preview-img');
    const video = document.getElementById('preview-video');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const url = URL.createObjectURL(file);
        preview.style.display = 'block';

        if (file.type.startsWith('video/')) {
            video.src = url;
            video.style.display = 'block';
            img.style.display = 'none';
        } else {
            img.src = url;
            img.style.display = 'block';
            video.style.display = 'none';
        }
    }
}
</script>

@endsection