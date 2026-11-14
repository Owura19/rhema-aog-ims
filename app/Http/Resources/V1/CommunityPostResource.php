<?php

namespace App\Http\Resources\V1;

use App\Models\CommunityPost;
use App\Models\CommunityPostPhoto;
use App\Support\Community\Author;
use App\Support\Community\PhotoUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A community post as one viewer sees it. Exactly these keys. Expects author and
 * photos loaded (CommunityFeed::posts() does, with liked_by_me).
 *
 * @mixin CommunityPost
 */
class CommunityPostResource extends JsonResource
{
    public function __construct(CommunityPost $post, private readonly int $viewer)
    {
        parent::__construct($post);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'author'        => Author::of($this->author),
            'body'          => $this->body,
            'photos'        => $this->photos->map(fn (CommunityPostPhoto $photo) => [
                'id'        => $photo->id,
                'width'     => $photo->width,
                'height'    => $photo->height,
                'url'       => PhotoUrls::for($photo, CommunityPostPhoto::VARIANT_FULL, $this->viewer),
                'thumb_url' => PhotoUrls::for($photo, CommunityPostPhoto::VARIANT_THUMB, $this->viewer),
            ])->values()->all(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'edited_at'     => $this->edited_at?->toIso8601String(),
            'like_count'    => $this->like_count,
            'comment_count' => $this->comment_count,
            'liked_by_me'   => (bool) ($this->liked_by_me ?? false),
            'mine'          => $this->member_id === $this->viewer,
            'status'        => $this->isHidden() ? 'hidden' : 'visible',
        ];
    }
}
