<?php

namespace Tests\Feature\Api\Member\Concerns;

use App\Models\ChurchService;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * Builders for the member-feature API tests. These models have no factories,
 * so rows are created directly. Today is frozen at Monday 2026-11-09 10:00 (UTC).
 */
trait MemberApiHelpers
{
    protected Member $me;
    protected string $token;

    protected function freezeToday(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00'));
    }

    /** A member with a phone login and an app token; becomes $this->me. */
    protected function signInAsMember(array $memberAttributes = []): Member
    {
        $this->me = $this->member($memberAttributes);

        $user = User::factory()->create([
            'member_id'   => $this->me->id,
            'name'        => $this->me->full_name,
            'email'       => null,
            'login_phone' => '+233241234567',
        ]);
        $this->token = $user->createAppToken('Test phone')->plainTextToken;

        return $this->me;
    }

    protected function member(array $attributes = []): Member
    {
        return Member::create($attributes + [
            'first_name'        => 'Ama',
            'last_name'         => 'Mensah',
            'phone'             => '0241234567',
            'membership_status' => 'Active',
        ]);
    }

    /** A request as $this->me. Query strings go in $uri; $data is the JSON body. */
    protected function api(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->json($method, '/api/v1' . $uri, $data);
    }

    protected function gift(Member $member, array $attributes = []): Transaction
    {
        return Transaction::create($attributes + [
            'type'             => 'Tithe',
            'category'         => 'Income',
            'amount'           => '100.00',
            'member_id'        => $member->id,
            'transaction_date' => '2026-03-01',
            'payment_method'   => 'Cash',
            'status'           => 'Confirmed',
        ]);
    }

    protected function pledge(?Member $member, array $attributes = []): Pledge
    {
        $purpose = PledgePurpose::firstOrCreate(['name' => 'Building Project']);

        return Pledge::create($attributes + [
            'member_id'         => $member?->id,
            'pledge_purpose_id' => $purpose->id,
            'amount_pledged'    => '1000.00',
            'date_pledged'      => '2026-02-01',
            'status'            => 'Active',
        ]);
    }

    /** Goes through PledgePayment's created hook, which also records the matching Pledge gift. */
    protected function pay(Pledge $pledge, string $amount, string $date = '2026-03-01', string $method = 'Cash'): PledgePayment
    {
        return PledgePayment::create([
            'pledge_id'      => $pledge->id,
            'amount'         => $amount,
            'payment_date'   => $date,
            'payment_method' => $method,
        ]);
    }

    protected function event(array $attributes = []): Event
    {
        return Event::create($attributes + [
            'title'         => 'Youth Convention',
            'type'          => 'Convention',
            'start_date'    => '2026-11-20',
            'status'        => 'Published',
            'is_free'       => true,
            'ticket_price'  => 0,
            'rsvp_required' => false,
        ]);
    }

    protected function service(array $attributes = []): ChurchService
    {
        return ChurchService::create($attributes + [
            'name'         => 'Sunday First Service',
            'service_type' => 'Sunday Service',
            'service_date' => '2026-11-15',
            'start_time'   => '07:30',
            'status'       => 'Scheduled',
        ]);
    }

    protected function rsvp(Event $event, Member $member, int $guests = 1, string $status = 'Confirmed', array $attributes = []): EventRsvp
    {
        return EventRsvp::create($attributes + [
            'event_id'     => $event->id,
            'member_id'    => $member->id,
            'guests_count' => $guests,
            'status'       => $status,
        ]);
    }

    /** @return list<int> the ids in data, in order */
    protected function ids(TestResponse $response): array
    {
        return array_column($response->json('data'), 'id');
    }
}
