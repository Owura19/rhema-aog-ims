<?php

namespace App\Http\Resources\V1;

use App\Models\CommunityComment;
use App\Support\Community\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment as one viewer sees it. Exactly these keys. Expects author loaded.
 *
 * @mixin CommunityComment
 */
class CommunityCommentResource extends JsonResource
{
    public function __construct(CommunityComment $comment, private readonly int $viewer)
    {
        parent::__construct($comment);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'post_id'    => $this->post_id,
            'author'     => Author::of($this->author),
            'body'       => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'mine'       => $this->member_id === $this->viewer,
            'status'     => $this->isHidden() ? 'hidden' : 'visible',
        ];
    }
}
