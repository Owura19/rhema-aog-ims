<?php

namespace Tests\Feature\Api\Member;

use App\Models\Family;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Member\Concerns\MemberApiHelpers;
use Tests\TestCase;

/** GET /api/v1/me/profile */
class MeProfileTest extends TestCase
{
    use RefreshDatabase, MemberApiHelpers;

    public function test_it_returns_exactly_the_agreed_fields(): void
    {
        $family = Family::create([
            'family_name' => 'Mensah Family',
            'address'     => 'Family house address',
            'phone'       => '0302000000',
            'notes'       => 'Family note for staff',
        ]);

        $this->signInAsMember([
            'other_name'              => 'Serwaa',
            'email'                   => 'ama@example.com',
            'alt_phone'               => '0551234567',
            'gender'                  => 'Female',
            'date_of_birth'           => '1990-04-02',
            'occupation'              => 'Teacher',
            'employer'                => 'GES',
            'marital_status'          => 'Married',
            'residential_address'     => 'East Legon',
            'digital_address'         => 'GA-123-4567',
            'emergency_contact_name'  => 'Kojo Mensah',
            'emergency_contact_phone' => '0201234567',
            'date_joined'             => '2015-01-11',
            'date_baptized'           => '2016-06-05',
            'membership_status'       => 'Inactive',
            'member_type'             => 'Associate',
            'photo'                   => 'members/ama.jpg',
            'family_id'               => $family->id,
            'family_role'             => 'Mother',
            'fingerprint_id'          => 4321,
            'notes'                   => 'Staff-only note about this member',
        ]);

        $response = $this->api('GET', '/me/profile')->assertOk();

        $response->assertExactJson(['data' => [
            'id'                  => $this->me->id,
            'member_code'         => $this->me->member_id,
            'first_name'          => 'Ama',
            'other_name'          => 'Serwaa',
            'last_name'           => 'Mensah',
            'full_name'           => 'Ama Serwaa Mensah',
            'gender'              => 'Female',
            'date_of_birth'       => '1990-04-02',
            'marital_status'      => 'Married',
            'email'               => 'ama@example.com',
            'phone'               => '0241234567',
            'alt_phone'           => '0551234567',
            'occupation'          => 'Teacher',
            'employer'            => 'GES',
            'residential_address' => 'East Legon',
            'digital_address'     => 'GA-123-4567',
            'emergency_contact'   => ['name' => 'Kojo Mensah', 'phone' => '0201234567'],
            'date_joined'         => '2015-01-11',
            'date_baptized'       => '2016-06-05',
            'photo_url'           => asset('storage/members/ama.jpg'),
            'family'              => ['name' => 'Mensah Family', 'role' => 'Mother'],
        ]]);

        // assertExactJson already rules out extra keys; also make sure the hidden VALUES appear nowhere.
        // (fingerprint_id isn't string-checked: a number could legitimately occur inside an id.)
        foreach (['Staff-only note', 'Family note', 'Family house address', '0302000000', 'Inactive', 'Associate'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $response->getContent());
        }
    }

    public function test_no_photo_and_no_family_are_null(): void
    {
        $this->signInAsMember();

        $this->api('GET', '/me/profile')
            ->assertOk()
            ->assertJsonPath('data.photo_url', null)
            ->assertJsonPath('data.family', null)
            ->assertJsonPath('data.emergency_contact', ['name' => null, 'phone' => null]);
    }
}
