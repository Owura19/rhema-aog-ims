<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use App\Models\User;
use App\Services\Auth\MemberPhoneDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** App\Services\Auth\MemberPhoneDirectory — who a phone number belongs to, and masked names. */
class MemberPhoneDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private const E164 = '+233241234567';

    private MemberPhoneDirectory $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = $this->app->make(MemberPhoneDirectory::class);
    }

    private function member(?string $phone, string $status = 'Active', array $extra = []): Member
    {
        return Member::create(['first_name' => 'Ama', 'last_name' => 'Mensah', 'phone' => $phone, 'membership_status' => $status] + $extra);
    }

    public function test_matches_every_spelling_of_the_number_and_nothing_else(): void
    {
        $matching = collect(['0241234567', '024 123 4567', '024-123-4567', '+233241234567', '233241234567', '(024) 123.4567'])
            ->map(fn (string $phone) => $this->member($phone)->id);

        foreach (['0241234568', '05412345678', '1241234567', '0551234567', null, ''] as $phone) {
            $this->member($phone);   // different number, unparseable, or empty
        }

        $this->assertSame($matching->all(), $this->directory->eligibleMembers(self::E164)->pluck('id')->all());
    }

    public static function statuses(): array
    {
        return [
            'Active'      => ['Active', true],
            'Inactive'    => ['Inactive', true],
            'Deceased'    => ['Deceased', false],
            'Transferred' => ['Transferred', false],
            'Visitor'     => ['Visitor', false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_active_or_inactive_members_are_eligible(string $status, bool $eligible): void
    {
        $this->member('0241234567', $status);

        $this->assertSame($eligible, $this->directory->eligibleMembers(self::E164)->isNotEmpty());
    }

    public function test_soft_deleted_members_are_not_eligible(): void
    {
        $this->member('0241234567')->delete();

        $this->assertTrue($this->directory->eligibleMembers(self::E164)->isEmpty());
    }

    public function test_members_with_an_account_are_left_out_of_the_activation_list(): void
    {
        $free = $this->member('0241234567');
        $taken = $this->member('0241234567');
        User::factory()->create(['member_id' => $taken->id]);

        $this->assertSame([$free->id], $this->directory->eligibleMembersWithoutAccount(self::E164)->pluck('id')->all());
        $this->assertCount(2, $this->directory->eligibleMembers(self::E164));
    }

    public function test_is_registered_checks_login_phone(): void
    {
        $this->assertFalse($this->directory->isRegistered(self::E164));

        User::factory()->create(['email' => null, 'login_phone' => self::E164]);

        $this->assertTrue($this->directory->isRegistered(self::E164));
        $this->assertFalse($this->directory->isRegistered('+233559999999'));
    }

    public static function names(): array
    {
        $openO = mb_chr(0x0186, 'UTF-8');   // Akan letter, built from its code point

        return [
            'two names'            => ['Ama Mensah', 'A*** M***'],
            'three names'          => ['Ama Serwaa Mensah', 'A*** S*** M***'],
            'one name'             => ['Kofi', 'K***'],
            'extra spaces'         => ['  Ama   Mensah ', 'A*** M***'],
            'non-ASCII first letter'=> [$openO . 'kyeame Asare', $openO . '*** A***'],
        ];
    }

    #[DataProvider('names')]
    public function test_names_are_masked_to_first_letters(string $fullName, string $masked): void
    {
        $this->assertSame($masked, MemberPhoneDirectory::mask($fullName));
    }
}
