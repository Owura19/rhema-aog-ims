<?php

namespace App\Support\Community;

use App\Models\CommunityPostPhoto;
use Illuminate\Support\Facades\URL;

/**
 * Short-lived signed links to community photos. The viewer's member id is part of the
 * signed URL, so each request re-checks that this viewer may still see the photo
 * (post not deleted or hidden, no block either way) without needing the bearer token.
 * Signed relative to the path, so a proxy changing the host or scheme can't break them.
 */
final class PhotoUrls
{
    public static function for(CommunityPostPhoto $photo, string $variant, int $viewerMemberId): string
    {
        $path = URL::temporarySignedRoute(
            'api.v1.community.photos.show',
            now()->addMinutes((int) config('community.photos.url_minutes')),
            ['photo' => $photo->id, 'variant' => $variant, 'viewer' => $viewerMemberId],
            absolute: false,
        );

        return url($path);
    }
}
