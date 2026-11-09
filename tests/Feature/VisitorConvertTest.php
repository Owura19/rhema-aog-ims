<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Visitor -> member conversion:
 *  - the visitor's address and the conversion date land in the member's
 *    residential_address / date_joined columns (they were silently dropped);
 *  - visitors/show still renders when the converted visitor's member is gone.
 */
class VisitorConvertTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (['create members', 'view members', 'view visitors'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['create members', 'view members', 'view visitors']);

        $registrar->forgetCachedPermissions();
    }

    public function test_convert_copies_address_and_sets_date_joined(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->setTime(10, 30));

        $visitor = Visitor::create([
            'first_name'       => 'Ama',
            'last_name'        => 'Mensah',
            'phone'            => '0241234567',
            'address'          => '12 Palm Street, Accra',
            'visit_date'       => '2026-10-04',
            'visit_type'       => 'First Time',
            'follow_up_status' => 'Pending',
        ]);

        $response = $this->actingAs($this->user)->post(route('visitors.convert', $visitor));

        $visitor->refresh();
        $member = $visitor->member;

        $this->assertNotNull($member, 'A member should be created and linked to the visitor.');
        $response->assertRedirect(route('members.show', $member));

        $this->assertSame('12 Palm Street, Accra', $member->residential_address);
        $this->assertSame('2026-11-08', $member->date_joined?->toDateString());

        $this->assertSame('Ama', $member->first_name);
        $this->assertSame('Mensah', $member->last_name);
        $this->assertSame('0241234567', $member->phone);
        $this->assertSame('Active', $member->membership_status);

        $this->assertTrue($visitor->converted_to_member);
        $this->assertSame('Joined', $visitor->follow_up_status);
    }

    public static function memberRemovals(): array
    {
        return [
            'member soft-deleted' => [false],
            'member hard-deleted' => [true],
        ];
    }

    #[DataProvider('memberRemovals')]
    public function test_show_renders_when_converted_visitors_member_was_deleted(bool $forceDelete): void
    {
        $member = Member::create(['first_name' => 'Kofi', 'last_name' => 'Boateng']);

        $visitor = Visitor::create([
            'first_name'          => 'Kofi',
            'last_name'           => 'Boateng',
            'visit_date'          => '2026-10-04',
            'visit_type'          => 'First Time',
            'follow_up_status'    => 'Joined',
            'converted_to_member' => true,
            'member_id'           => $member->id,
        ]);

        $memberUrl = route('members.show', $member);

        $forceDelete ? $member->forceDelete() : $member->delete();

        $response = $this->actingAs($this->user)->get(route('visitors.show', $visitor));

        $response->assertOk();
        $response->assertSee('Kofi Boateng');
        $response->assertDontSee($memberUrl);
    }
}
