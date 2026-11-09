<?php

namespace Tests\Unit;

use App\Models\Member;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Member::full_name must be single-spaced and trimmed, whatever the name parts
 * contain. (Previously an empty other_name left a double space: "Ama  Mensah".)
 * Pure unit test: models are built in memory, nothing is saved.
 */
class MemberFullNameTest extends TestCase
{
    public static function names(): array
    {
        return [
            'null other_name'             => ['Ama', null, 'Mensah', 'Ama Mensah'],
            'empty other_name'            => ['Ama', '', 'Mensah', 'Ama Mensah'],
            'whitespace-only other_name'  => ['Ama', '   ', 'Mensah', 'Ama Mensah'],
            'real other_name'             => ['Ama', 'Serwaa', 'Mensah', 'Ama Serwaa Mensah'],
            'padded parts'                => [' Ama ', '  Serwaa ', 'Mensah  ', 'Ama Serwaa Mensah'],
            'internal double space'       => ['Ama', 'Serwaa  Akua', 'Mensah', 'Ama Serwaa Akua Mensah'],
            'tabs and newlines'           => ["Ama\t", null, "\nMensah", 'Ama Mensah'],
            'null first_name'             => [null, null, 'Mensah', 'Mensah'],
        ];
    }

    #[DataProvider('names')]
    public function test_full_name_is_single_spaced_and_trimmed(?string $first, ?string $other, ?string $last, string $expected): void
    {
        $member = new Member(['first_name' => $first, 'other_name' => $other, 'last_name' => $last]);

        $this->assertSame($expected, $member->full_name);
    }
}
