<?php

namespace Tests\Feature\Api\Member;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** Every member-feature route needs a token, an active login and a linked, existing member. */
class MemberAccessTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    public static function routes(): array
    {
        return [
            'profile'     => ['GET', '/me/profile'],
            'giving'      => ['GET', '/me/giving'],
            'pledges'     => ['GET', '/me/pledges'],
            'events'      => ['GET', '/events'],
            'event'       => ['GET', '/events/1'],
            'rsvp'        => ['POST', '/events/1/rsvp'],
            'cancel rsvp' => ['DELETE', '/events/1/rsvp'],
        ];
    }

    public function test_the_routes_exist_with_their_names(): void
    {
        foreach (['me.profile', 'me.giving', 'me.pledges', 'events.index', 'events.show', 'events.rsvp.store', 'events.rsvp.destroy'] as $name) {
            $this->assertTrue(Route::has("api.v1.{$name}"), "missing route api.v1.{$name}");
        }
    }

    #[DataProvider('routes')]
    public function test_it_needs_a_token(string $method, string $uri): void
    {
        $this->json($method, '/api/v1' . $uri)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    #[DataProvider('routes')]
    public function test_a_deactivated_login_is_refused(string $method, string $uri): void
    {
        $member = $this->member();
        $user = User::factory()->create(['member_id' => $member->id, 'is_active' => false]);
        $this->token = $user->createAppToken('Phone')->plainTextToken;

        $this->api($method, $uri)->assertForbidden()->assertJsonPath('code', 'account_inactive');
    }

    #[DataProvider('routes')]
    public function test_a_login_without_a_member_record_is_refused(string $method, string $uri): void
    {
        $this->token = User::factory()->create(['member_id' => null])->createAppToken('Phone')->plainTextToken;

        $this->api($method, $uri)->assertForbidden()->assertJsonPath('code', 'member_link_required');
    }

    #[DataProvider('routes')]
    public function test_a_soft_deleted_member_is_refused(string $method, string $uri): void
    {
        $this->signInAsMember();
        $this->me->delete();

        $this->api($method, $uri)->assertForbidden()->assertJsonPath('code', 'member_link_required');
    }
}
