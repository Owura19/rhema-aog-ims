<?php

namespace Tests\Unit;

use App\Models\Member;
use App\Services\Harvest\NameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NameMatcherTest extends TestCase
{
    private function member(string $first, string $last, ?string $other = null): Member
    {
        return (new Member())->forceFill(['first_name' => $first, 'other_name' => $other, 'last_name' => $last]);
    }

    public static function matching(): array
    {
        return [
            'same'                 => ['Kofi Mensah'],
            'case and spaces'      => ['  kofi   MENSAH '],
            'reversed'             => ['Mensah Kofi'],
            'with other name'      => ['Kofi Kwame Mensah'],
            'other name reordered' => ['Mensah Kofi Kwame'],
            'Mr.'                  => ['Mr. Kofi Mensah'],
            'M/M'                  => ['M/M Kofi Mensah'],
            'Rev Dr'               => ['Rev. Dr. Kofi Mensah'],
            'Elder'                => ['Elder Kofi Mensah'],
            'Deacon'               => ['Deacon Kofi Mensah'],
            'Pastor'               => ['Pastor Kofi Mensah'],
            'punctuation'          => ['Kofi-Mensah,'],
        ];
    }

    #[DataProvider('matching')]
    public function test_names_that_match_the_member(string $name): void
    {
        $this->assertTrue(NameMatcher::matchesMember($this->member('Kofi', 'Mensah', 'Kwame'), $name));
    }

    public static function notMatching(): array
    {
        return [
            'first name only' => ['Kofi'],
            'other person'    => ['Kofi Boateng'],
            'extra word'      => ['Kofi Mensah Junior'],
            'only a title'    => ['Mr'],
            'empty'           => [''],
            'title mid-name'  => ['Kofi Mr Mensah'],
        ];
    }

    #[DataProvider('notMatching')]
    public function test_names_that_do_not_match(string $name): void
    {
        $this->assertFalse(NameMatcher::matchesMember($this->member('Kofi', 'Mensah', 'Kwame'), $name));
    }

    public function test_same_and_key(): void
    {
        $this->assertTrue(NameMatcher::same('Mrs Ama  Owusu', 'owusu ama'));
        $this->assertFalse(NameMatcher::same('', ''));
        $this->assertSame('ama owusu', NameMatcher::key('Mrs. AMA Owusu'));
    }
}
