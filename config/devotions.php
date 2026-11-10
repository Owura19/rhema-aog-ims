<?php

/*
|--------------------------------------------------------------------------
| Daily devotions
|--------------------------------------------------------------------------
| English is the source language. Translations are drafted (optionally by AI,
| per language below) and must be approved by a reviewer before the app shows
| them; until then the app shows English. Verse text always comes from the
| KJV dataset; reviewers paste local-language verses.
*/

return [

    'default_locale' => 'en',

    /*
    | Translation languages. ai_translation: may AI draft this language?
    | (Only Hausa is on: general-purpose models are weak at Ga and Ewe and
    | uneven at Twi. Turn a language on once reviewers trust its drafts.)
    */
    'translations' => [
        'tw'  => ['name' => 'Twi',   'ai_translation' => false],
        'gaa' => ['name' => 'Ga',    'ai_translation' => false],
        'ee'  => ['name' => 'Ewe',   'ai_translation' => false],
        'ha'  => ['name' => 'Hausa', 'ai_translation' => true],
    ],

    'limits' => [
        // AI drafts are rejected outside these limits.
        'ai' => [
            'reflection_words' => [250, 350],
            'prayer_words'     => [40, 80],
            'thought_chars'    => 140,
        ],
        // Staff-written devotions: outside this band the form shows a warning (approval is still allowed).
        'staff_warning' => [
            'reflection_words' => [200, 450],
        ],
    ],

    'ai' => [
        // 'anthropic' or 'groq'. `?:` so an empty DEVOTIONS_AI_PROVIDER= line still means the default.
        'provider' => env('DEVOTIONS_AI_PROVIDER') ?: 'anthropic',
    ],

];
