<?php

namespace Tests\Feature\Biometric;

use App\Models\Member;
use App\Models\User;

/** A fingerprint ID belongs to one member; the duplicate report is read-only and names nobody. */
class FingerprintIdTest extends BiometricTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create()->assignRole('Super Admin');
    }

    private function newMember(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('members.store'), $overrides + [
            'first_name' => 'New', 'last_name' => 'Person', 'gender' => 'Male', 'membership_status' => 'Active', 'member_type' => 'Full Member',
        ]);
    }

    private function editMember(Member $member, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('members.update', $member), $overrides + ['first_name' => $member->first_name, 'last_name' => $member->last_name]);
    }

    public function test_creating_a_member_with_a_taken_id_is_refused_and_names_the_owner(): void
    {
        $owner = $this->member(123, 'Abena', 'Owusu');

        $this->newMember(['fingerprint_id' => '123'])->assertSessionHasErrors(['fingerprint_id' => "Fingerprint ID 123 is already assigned to Abena Owusu ({$owner->member_id})."]);
        $this->assertSame(1, Member::count());

        $this->newMember(['fingerprint_id' => '124'])->assertSessionHasNoErrors();
        $this->assertSame(2, Member::count());
        $this->newMember(['first_name' => 'No', 'last_name' => 'Id'])->assertSessionHasNoErrors();   // an ID is optional
        $this->newMember(['first_name' => 'Blank', 'last_name' => 'Id', 'fingerprint_id' => ''])->assertSessionHasNoErrors();
        $this->assertSame(4, Member::count());
    }

    public function test_editing_cannot_take_another_members_id_but_may_keep_its_own(): void
    {
        $a = $this->member(10, 'Ama', 'A');
        $b = $this->member(20, 'Kofi', 'B');

        $this->editMember($b, ['fingerprint_id' => '10'])->assertSessionHasErrors(['fingerprint_id' => "Fingerprint ID 10 is already assigned to Ama A ({$a->member_id})."]);
        $this->assertSame(20, $b->fresh()->fingerprint_id);

        $this->editMember($b, ['fingerprint_id' => '20', 'first_name' => 'Kofi Renamed'])->assertSessionHasNoErrors();   // own ID: fine
        $this->assertSame(['Kofi Renamed', 20], [$b->fresh()->first_name, $b->fresh()->fingerprint_id]);

        $this->editMember($b, ['fingerprint_id' => '21'])->assertSessionHasNoErrors();
        $this->assertSame(21, $b->fresh()->fingerprint_id);
        $this->editMember($a, ['fingerprint_id' => '20'])->assertSessionHasNoErrors();   // 20 is free now
        $this->assertSame(20, $a->fresh()->fingerprint_id);
    }

    public function test_the_edit_form_now_saves_the_fingerprint_id_and_can_clear_it(): void
    {
        $member = $this->member(null);

        $this->actingAs($this->admin)->get(route('members.edit', $member))->assertOk()->assertSee('name="fingerprint_id"', false);
        $this->editMember($member, ['fingerprint_id' => '77'])->assertSessionHasNoErrors();
        $this->assertSame(77, $member->fresh()->fingerprint_id);

        $this->editMember($member, ['fingerprint_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($member->fresh()->fingerprint_id);
    }

    public function test_the_id_must_be_a_positive_whole_number(): void
    {
        foreach (['abc', '0', '-5', '1.5', '99999999999'] as $bad) {
            $this->newMember(['fingerprint_id' => $bad])->assertSessionHasErrors('fingerprint_id');
        }
        $this->assertSame(0, Member::count());
    }

    public function test_a_deleted_members_id_can_be_reused(): void
    {
        $this->member(55)->delete();

        $this->newMember(['fingerprint_id' => '55'])->assertSessionHasNoErrors();
        $this->assertSame(1, Member::count());
    }

    public function test_there_is_no_database_unique_index_so_existing_duplicates_survive(): void
    {
        $this->member(9, 'One');
        $this->member(9, 'Two');

        $this->assertSame(2, Member::where('fingerprint_id', 9)->count());
    }

    public function test_the_duplicate_report_lists_ids_and_counts_only(): void
    {
        $this->member(9, 'Secret', 'Name');
        $this->member(9, 'Hidden', 'Person');
        $this->member(9, 'Third', 'Dup');
        $this->member(30, 'Pair', 'One');
        $this->member(30, 'Pair', 'Two');
        $this->member(31, 'Single', 'Fine');
        $this->member(null, 'No', 'Id');

        $this->artisan('biometric:duplicate-ids')->assertSuccessful()
            ->expectsOutputToContain('Fingerprint IDs shared by more than one member: 2')
            ->expectsOutputToContain('ID 9: 3 members')
            ->expectsOutputToContain('ID 30: 2 members')
            ->doesntExpectOutputToContain('Secret')->doesntExpectOutputToContain('Hidden')->doesntExpectOutputToContain('Pair')->doesntExpectOutputToContain('ID 31');

        $this->assertSame(7, Member::count());   // read-only
    }

    public function test_the_duplicate_report_with_none(): void
    {
        $this->member(1);
        $this->member(2);

        $this->artisan('biometric:duplicate-ids')->assertSuccessful()->expectsOutputToContain('No duplicate fingerprint IDs.');
    }
}
