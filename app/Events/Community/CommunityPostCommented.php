<?php

namespace App\Events\Community;

use App\Models\CommunityComment;
use Illuminate\Foundation\Events\Dispatchable;

/** Someone commented on a post. No listeners yet: a "new comment" push notification can hook in here. */
final class CommunityPostCommented
{
    use Dispatchable;

    public function __construct(public readonly CommunityComment $comment)
    {
    }
}
