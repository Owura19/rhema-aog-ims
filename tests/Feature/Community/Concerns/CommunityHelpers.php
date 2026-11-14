<?php

namespace Tests\Feature\Community\Concerns;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityRuleAcceptance;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;

/** Builders for the community API tests. Now is Sunday 2026-11-15 10:00 (UTC). */
trait CommunityHelpers
{
    use MemberApiHelpers;

    protected function setUpCommunity(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        Storage::fake('community');
    }

    /** $this->me: an adult (born 1990) who has accepted the current rules. */
    protected function signInAsParticipant(array $memberAttributes = []): Member
    {
        $this->signInAsMember($memberAttributes + ['date_of_birth' => '1990-05-01']);
        $this->accept($this->me);

        return $this->me;
    }

    protected function accept(Member $member, bool $adult = false): CommunityRuleAcceptance
    {
        return CommunityRuleAcceptance::create([
            'member_id'       => $member->id,
            'rules_version'   => config('community.rules.version'),
            'adult_confirmed' => $adult,
            'accepted_at'     => now(),
        ]);
    }

    protected function other(string $first = 'Kofi', array $attributes = []): Member
    {
        return $this->member($attributes + ['first_name' => $first, 'last_name' => 'Owusu', 'phone' => '0201112223']);
    }

    protected function makePost(Member $author, array $attributes = []): CommunityPost
    {
        return CommunityPost::create($attributes + ['member_id' => $author->id, 'body' => "Post by {$author->first_name}"]);
    }

    protected function makeComment(CommunityPost $post, Member $author, array $attributes = []): CommunityComment
    {
        $comment = CommunityComment::create($attributes + ['post_id' => $post->id, 'member_id' => $author->id, 'body' => 'Amen']);
        $post->increment('comment_count');

        return $comment;
    }

    /** Switch the bearer token to a new member's login (or a staff login with no member). */
    protected function actAs(?Member $member): void
    {
        $user = User::factory()->create(['member_id' => $member?->id, 'email' => null, 'login_phone' => null]);
        $this->token = $user->createAppToken('Phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        if ($member) {
            $this->me = $member;
        }
    }
}
