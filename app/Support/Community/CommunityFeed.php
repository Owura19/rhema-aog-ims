<?php

namespace App\Support\Community;

use App\Models\CommunityComment;
use App\Models\CommunityMemberBlock;
use App\Models\CommunityPost;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

/**
 * What one member may see. Never: deleted posts or comments, those of deleted members,
 * those of members blocked in either direction, or hidden ones (except the viewer's own,
 * shown with status "hidden").
 */
final class CommunityFeed
{
    /** @var list<int> */
    private array $hiddenMembers;

    public function __construct(public readonly int $viewer)
    {
        $this->hiddenMembers = CommunityMemberBlock::hiddenFrom($viewer);
    }

    public static function for(Member $member): self
    {
        return new self($member->id);
    }

    /** Visible posts, with author, photos and liked_by_me loaded. */
    public function posts(): Builder
    {
        return $this->visible(CommunityPost::query())
            ->with(['author', 'photos'])
            ->withExists(['likes as liked_by_me' => fn (Builder $q) => $q->where('member_id', $this->viewer)]);
    }

    /** Visible comments of a (visible) post, with author loaded. */
    public function comments(CommunityPost $post): Builder
    {
        return $this->visible(CommunityComment::query()->where('post_id', $post->id))->with('author');
    }

    /** A visible comment by id, its post visible too; null otherwise. */
    public function comment(int $id): ?CommunityComment
    {
        $comment = $this->visible(CommunityComment::query())->find($id);

        return $comment && $this->visible(CommunityPost::query())->whereKey($comment->post_id)->exists() ? $comment : null;
    }

    public function hides(int $memberId): bool
    {
        return in_array($memberId, $this->hiddenMembers, true);
    }

    private function visible(Builder $query): Builder
    {
        return $query
            ->whereIn('member_id', Member::query()->select('id'))   // excludes deleted members
            ->when($this->hiddenMembers !== [], fn (Builder $q) => $q->whereNotIn('member_id', $this->hiddenMembers))
            ->where(fn (Builder $q) => $q->whereNull('hidden_at')->orWhere('member_id', $this->viewer));
    }
}
