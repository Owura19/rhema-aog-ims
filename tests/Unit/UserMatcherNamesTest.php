<?php

namespace Tests\Unit;

use App\Services\Biometric\UserMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** How device names and member names are reduced to comparable word sets (fake names only). */
class UserMatcherNamesTest extends TestCase
{
    public static function names(): array
    {
        return [
            'plain'                    => ['Alpha Bravo', ['alpha', 'bravo']],
            'word order does not matter' => ['Bravo Alpha', ['alpha', 'bravo']],
            'case and spaces'          => ["  ALPHA    bravo \t", ['alpha', 'bravo']],
            'punctuation becomes space' => ['Alpha, Bravo.', ['alpha', 'bravo']],
            'hyphen splits'            => ['Alpha-Bravo', ['alpha', 'bravo']],
            'apostrophe joins'         => ["O'Charlie Alpha", ['alpha', 'ocharlie']],
            'curly apostrophe joins'   => ['O’Charlie Alpha', ['alpha', 'ocharlie']],
            'a leading title'          => ['Rev. Alpha Bravo', ['alpha', 'bravo']],
            'titles anywhere'          => ['Alpha Dr Bravo Pastor', ['alpha', 'bravo']],
            'every listed title'       => ['Mr Mrs Miss Rev Pastor Deacon Elder Dr Apostle Evangelist Bro Sis Alpha', ['alpha']],
            'title with dots'          => ['Dr. Evangelist. Alpha', ['alpha']],
            'repeated word once'       => ['Alpha Alpha Bravo', ['alpha', 'bravo']],
            'only titles'              => ['Rev Dr', []],
            'empty'                    => ['', []],
            'a name that merely contains a title letters' => ['Mrsa Elderly Alpha', ['alpha', 'elderly', 'mrsa']],   // whole words only
            'unicode letters'          => ['Émile Zoë', ['zoë', 'émile']],
        ];
    }

    #[DataProvider('names')]
    public function test_names_become_sorted_word_sets(string $name, array $expected): void
    {
        $this->assertSame($expected, UserMatcher::tokens($name));
    }

    public function test_null_is_an_empty_name(): void
    {
        $this->assertSame([], UserMatcher::tokens(null));
    }

    public static function ids(): array
    {
        return [
            ['1', 1], ['123', 123], [' 45 ', 45], ['0007', 7], ['2147483647', 2147483647],
            ['0', null], ['2147483648', null], ['-5', null], ['A12', null], ['12A', null], ['1.5', null], ['', null], [null, null], ['٣', null],
        ];
    }

    #[DataProvider('ids')]
    public function test_device_user_ids(?string $raw, ?int $expected): void
    {
        $this->assertSame($expected, UserMatcher::parseId($raw));
    }
}
