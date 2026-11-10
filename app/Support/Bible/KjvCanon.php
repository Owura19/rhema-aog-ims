<?php

namespace App\Support\Bible;

/**
 * The 66 books of the KJV (protocanon) in canonical order. A book's id in bible_books is its
 * position here (Genesis 1 ... Revelation 66).
 *
 * code   - the standard USFM code we store
 * ebible - the code eBible.org uses in eng-kjv2006_vpl.txt (differs for 11 books, e.g. JOH/JHN)
 * chapters, verses - the standard KJV counts (1,189 chapters, 31,102 verses in total)
 */
final class KjvCanon
{
    public const TOTAL_CHAPTERS = 1189;
    public const TOTAL_VERSES   = 31102;

    public const BOOKS = [
        ['code' => 'GEN', 'ebible' => 'GEN', 'name' => 'Genesis',         'testament' => 'OT', 'chapters' => 50,  'verses' => 1533],
        ['code' => 'EXO', 'ebible' => 'EXO', 'name' => 'Exodus',          'testament' => 'OT', 'chapters' => 40,  'verses' => 1213],
        ['code' => 'LEV', 'ebible' => 'LEV', 'name' => 'Leviticus',       'testament' => 'OT', 'chapters' => 27,  'verses' => 859],
        ['code' => 'NUM', 'ebible' => 'NUM', 'name' => 'Numbers',         'testament' => 'OT', 'chapters' => 36,  'verses' => 1288],
        ['code' => 'DEU', 'ebible' => 'DEU', 'name' => 'Deuteronomy',     'testament' => 'OT', 'chapters' => 34,  'verses' => 959],
        ['code' => 'JOS', 'ebible' => 'JOS', 'name' => 'Joshua',          'testament' => 'OT', 'chapters' => 24,  'verses' => 658],
        ['code' => 'JDG', 'ebible' => 'JDG', 'name' => 'Judges',          'testament' => 'OT', 'chapters' => 21,  'verses' => 618],
        ['code' => 'RUT', 'ebible' => 'RUT', 'name' => 'Ruth',            'testament' => 'OT', 'chapters' => 4,   'verses' => 85],
        ['code' => '1SA', 'ebible' => '1SA', 'name' => '1 Samuel',        'testament' => 'OT', 'chapters' => 31,  'verses' => 810],
        ['code' => '2SA', 'ebible' => '2SA', 'name' => '2 Samuel',        'testament' => 'OT', 'chapters' => 24,  'verses' => 695],
        ['code' => '1KI', 'ebible' => '1KI', 'name' => '1 Kings',         'testament' => 'OT', 'chapters' => 22,  'verses' => 816],
        ['code' => '2KI', 'ebible' => '2KI', 'name' => '2 Kings',         'testament' => 'OT', 'chapters' => 25,  'verses' => 719],
        ['code' => '1CH', 'ebible' => '1CH', 'name' => '1 Chronicles',    'testament' => 'OT', 'chapters' => 29,  'verses' => 942],
        ['code' => '2CH', 'ebible' => '2CH', 'name' => '2 Chronicles',    'testament' => 'OT', 'chapters' => 36,  'verses' => 822],
        ['code' => 'EZR', 'ebible' => 'EZR', 'name' => 'Ezra',            'testament' => 'OT', 'chapters' => 10,  'verses' => 280],
        ['code' => 'NEH', 'ebible' => 'NEH', 'name' => 'Nehemiah',        'testament' => 'OT', 'chapters' => 13,  'verses' => 406],
        ['code' => 'EST', 'ebible' => 'EST', 'name' => 'Esther',          'testament' => 'OT', 'chapters' => 10,  'verses' => 167],
        ['code' => 'JOB', 'ebible' => 'JOB', 'name' => 'Job',             'testament' => 'OT', 'chapters' => 42,  'verses' => 1070],
        ['code' => 'PSA', 'ebible' => 'PSA', 'name' => 'Psalms',          'testament' => 'OT', 'chapters' => 150, 'verses' => 2461],
        ['code' => 'PRO', 'ebible' => 'PRO', 'name' => 'Proverbs',        'testament' => 'OT', 'chapters' => 31,  'verses' => 915],
        ['code' => 'ECC', 'ebible' => 'ECC', 'name' => 'Ecclesiastes',    'testament' => 'OT', 'chapters' => 12,  'verses' => 222],
        ['code' => 'SNG', 'ebible' => 'SOL', 'name' => 'Song of Solomon', 'testament' => 'OT', 'chapters' => 8,   'verses' => 117],
        ['code' => 'ISA', 'ebible' => 'ISA', 'name' => 'Isaiah',          'testament' => 'OT', 'chapters' => 66,  'verses' => 1292],
        ['code' => 'JER', 'ebible' => 'JER', 'name' => 'Jeremiah',        'testament' => 'OT', 'chapters' => 52,  'verses' => 1364],
        ['code' => 'LAM', 'ebible' => 'LAM', 'name' => 'Lamentations',    'testament' => 'OT', 'chapters' => 5,   'verses' => 154],
        ['code' => 'EZK', 'ebible' => 'EZE', 'name' => 'Ezekiel',         'testament' => 'OT', 'chapters' => 48,  'verses' => 1273],
        ['code' => 'DAN', 'ebible' => 'DAN', 'name' => 'Daniel',          'testament' => 'OT', 'chapters' => 12,  'verses' => 357],
        ['code' => 'HOS', 'ebible' => 'HOS', 'name' => 'Hosea',           'testament' => 'OT', 'chapters' => 14,  'verses' => 197],
        ['code' => 'JOL', 'ebible' => 'JOE', 'name' => 'Joel',            'testament' => 'OT', 'chapters' => 3,   'verses' => 73],
        ['code' => 'AMO', 'ebible' => 'AMO', 'name' => 'Amos',            'testament' => 'OT', 'chapters' => 9,   'verses' => 146],
        ['code' => 'OBA', 'ebible' => 'OBA', 'name' => 'Obadiah',         'testament' => 'OT', 'chapters' => 1,   'verses' => 21],
        ['code' => 'JON', 'ebible' => 'JON', 'name' => 'Jonah',           'testament' => 'OT', 'chapters' => 4,   'verses' => 48],
        ['code' => 'MIC', 'ebible' => 'MIC', 'name' => 'Micah',           'testament' => 'OT', 'chapters' => 7,   'verses' => 105],
        ['code' => 'NAM', 'ebible' => 'NAH', 'name' => 'Nahum',           'testament' => 'OT', 'chapters' => 3,   'verses' => 47],
        ['code' => 'HAB', 'ebible' => 'HAB', 'name' => 'Habakkuk',        'testament' => 'OT', 'chapters' => 3,   'verses' => 56],
        ['code' => 'ZEP', 'ebible' => 'ZEP', 'name' => 'Zephaniah',       'testament' => 'OT', 'chapters' => 3,   'verses' => 53],
        ['code' => 'HAG', 'ebible' => 'HAG', 'name' => 'Haggai',          'testament' => 'OT', 'chapters' => 2,   'verses' => 38],
        ['code' => 'ZEC', 'ebible' => 'ZEC', 'name' => 'Zechariah',       'testament' => 'OT', 'chapters' => 14,  'verses' => 211],
        ['code' => 'MAL', 'ebible' => 'MAL', 'name' => 'Malachi',         'testament' => 'OT', 'chapters' => 4,   'verses' => 55],
        ['code' => 'MAT', 'ebible' => 'MAT', 'name' => 'Matthew',         'testament' => 'NT', 'chapters' => 28,  'verses' => 1071],
        ['code' => 'MRK', 'ebible' => 'MAR', 'name' => 'Mark',            'testament' => 'NT', 'chapters' => 16,  'verses' => 678],
        ['code' => 'LUK', 'ebible' => 'LUK', 'name' => 'Luke',            'testament' => 'NT', 'chapters' => 24,  'verses' => 1151],
        ['code' => 'JHN', 'ebible' => 'JOH', 'name' => 'John',            'testament' => 'NT', 'chapters' => 21,  'verses' => 879],
        ['code' => 'ACT', 'ebible' => 'ACT', 'name' => 'Acts',            'testament' => 'NT', 'chapters' => 28,  'verses' => 1007],
        ['code' => 'ROM', 'ebible' => 'ROM', 'name' => 'Romans',          'testament' => 'NT', 'chapters' => 16,  'verses' => 433],
        ['code' => '1CO', 'ebible' => '1CO', 'name' => '1 Corinthians',   'testament' => 'NT', 'chapters' => 16,  'verses' => 437],
        ['code' => '2CO', 'ebible' => '2CO', 'name' => '2 Corinthians',   'testament' => 'NT', 'chapters' => 13,  'verses' => 257],
        ['code' => 'GAL', 'ebible' => 'GAL', 'name' => 'Galatians',       'testament' => 'NT', 'chapters' => 6,   'verses' => 149],
        ['code' => 'EPH', 'ebible' => 'EPH', 'name' => 'Ephesians',       'testament' => 'NT', 'chapters' => 6,   'verses' => 155],
        ['code' => 'PHP', 'ebible' => 'PHI', 'name' => 'Philippians',     'testament' => 'NT', 'chapters' => 4,   'verses' => 104],
        ['code' => 'COL', 'ebible' => 'COL', 'name' => 'Colossians',      'testament' => 'NT', 'chapters' => 4,   'verses' => 95],
        ['code' => '1TH', 'ebible' => '1TH', 'name' => '1 Thessalonians', 'testament' => 'NT', 'chapters' => 5,   'verses' => 89],
        ['code' => '2TH', 'ebible' => '2TH', 'name' => '2 Thessalonians', 'testament' => 'NT', 'chapters' => 3,   'verses' => 47],
        ['code' => '1TI', 'ebible' => '1TI', 'name' => '1 Timothy',       'testament' => 'NT', 'chapters' => 6,   'verses' => 113],
        ['code' => '2TI', 'ebible' => '2TI', 'name' => '2 Timothy',       'testament' => 'NT', 'chapters' => 4,   'verses' => 83],
        ['code' => 'TIT', 'ebible' => 'TIT', 'name' => 'Titus',           'testament' => 'NT', 'chapters' => 3,   'verses' => 46],
        ['code' => 'PHM', 'ebible' => 'PHM', 'name' => 'Philemon',        'testament' => 'NT', 'chapters' => 1,   'verses' => 25],
        ['code' => 'HEB', 'ebible' => 'HEB', 'name' => 'Hebrews',         'testament' => 'NT', 'chapters' => 13,  'verses' => 303],
        ['code' => 'JAS', 'ebible' => 'JAM', 'name' => 'James',           'testament' => 'NT', 'chapters' => 5,   'verses' => 108],
        ['code' => '1PE', 'ebible' => '1PE', 'name' => '1 Peter',         'testament' => 'NT', 'chapters' => 5,   'verses' => 105],
        ['code' => '2PE', 'ebible' => '2PE', 'name' => '2 Peter',         'testament' => 'NT', 'chapters' => 3,   'verses' => 61],
        ['code' => '1JN', 'ebible' => '1JO', 'name' => '1 John',          'testament' => 'NT', 'chapters' => 5,   'verses' => 105],
        ['code' => '2JN', 'ebible' => '2JO', 'name' => '2 John',          'testament' => 'NT', 'chapters' => 1,   'verses' => 13],
        ['code' => '3JN', 'ebible' => '3JO', 'name' => '3 John',          'testament' => 'NT', 'chapters' => 1,   'verses' => 14],
        ['code' => 'JUD', 'ebible' => 'JUD', 'name' => 'Jude',            'testament' => 'NT', 'chapters' => 1,   'verses' => 25],
        ['code' => 'REV', 'ebible' => 'REV', 'name' => 'Revelation',      'testament' => 'NT', 'chapters' => 22,  'verses' => 404],
    ];
}
