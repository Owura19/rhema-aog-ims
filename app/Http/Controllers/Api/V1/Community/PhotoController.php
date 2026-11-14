<?php

namespace App\Http\Controllers\Api\V1\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityMemberBlock;
use App\Models\CommunityPostPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /community/photos/{photo}/{full|thumb}?viewer=&expires=&signature=
 *
 * Reached only through a signed URL (PhotoUrls); no bearer token, so the app's image
 * component can load it directly. Each request re-checks the viewer may see the post:
 * not deleted, not hidden (unless it is the viewer's own), no block in either direction.
 * Anything else is the same 404.
 */
class PhotoController extends Controller
{
    public function show(Request $request, int $photo, string $variant): Response
    {
        $model = CommunityPostPhoto::with('post')->find($photo);
        $post = $model?->post;
        $viewer = (int) $request->query('viewer');

        abort_if(! $model || ! $post || $post->trashed(), 404);
        abort_if($post->isHidden() && $post->member_id !== $viewer, 404);
        abort_if(in_array($post->member_id, CommunityMemberBlock::hiddenFrom($viewer), true), 404);

        $disk = Storage::disk(config('community.photos.disk'));
        $path = $model->pathFor($variant);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type'           => 'image/jpeg',
            'Cache-Control'          => 'private, max-age=' . ((int) config('community.photos.url_minutes') * 60),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
