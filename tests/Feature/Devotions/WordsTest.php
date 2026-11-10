<?php

namespace Tests\Feature\Devotions;

use App\Support\Devotions\Words;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Words::staffLengthWarning(): outside 200–450 words a warning, with "word" or "words" as the number needs. */
class WordsTest extends TestCase
{
    public static function bodies(): array
    {
        return [
            'no words'     => ['', 'The reflection has 0 words;'],
            'one word'     => ['Grace', 'The reflection has 1 word;'],
            'two words'    => ['Amazing grace', 'The reflection has 2 words;'],
            'too long'     => [str_repeat('grace ', 451), 'The reflection has 451 words;'],
            'within limit' => [str_repeat('grace ', 300), null],
        ];
    }

    #[DataProvider('bodies')]
    public function test_the_length_warning_counts_words_in_singular_or_plural(string $body, ?string $start): void
    {
        $warning = Words::staffLengthWarning($body);

        if ($start === null) {
            $this->assertNull($warning);

            return;
        }

        $this->assertStringStartsWith($start, $warning);
        $this->assertStringEndsWith('the usual length is 200–450. It was saved anyway.', $warning);
    }
}
