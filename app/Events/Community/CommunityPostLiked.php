<?php

namespace App\Events\Community;

use App\Models\CommunityLike;
use Illuminate\Foundation\Events\Dispatchable;

/** Someone liked a post (only on a new like, not a repeat). No listeners yet. */
final class CommunityPostLiked
{
    use Dispatchable;

    public function __construct(public readonly CommunityLike $like)
    {
    }
}
