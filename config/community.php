<?php

/*
|--------------------------------------------------------------------------
| Members' community feed (mobile app)
|--------------------------------------------------------------------------
*/

return [

    // The rules members accept before they post. Changing the version asks everyone to accept again.
    'rules' => [
        'version' => '2026-11-draft',
        'title'   => 'Community Guidelines',
        'path'    => resource_path('community-rules.md'),
    ],

    // Members this age or older may post, comment and like (from members.date_of_birth).
    // Unknown date of birth: allowed once they tick "I am 18 or older" when accepting the rules.
    'minimum_age' => 18,

    // A post or comment is hidden automatically once this many different members report it.
    'auto_hide_reports' => 3,

    'photos' => [
        'disk'           => 'community',
        'max_per_post'   => 4,
        'max_kb'         => 8192,      // per photo, as uploaded
        'max_megapixels' => 40,        // refused before decoding (memory guard)
        'long_side'      => 1600,      // stored photo, JPEG
        'thumb_side'     => 640,       // stored thumbnail, JPEG
        'quality'        => 80,
        'url_minutes'    => 60,        // signed photo URLs last this long
    ],

    'feed' => [
        'default_limit' => 15,
        'max_limit'     => 30,
        'comments_on_post' => 20,
    ],

    // Per member. [max attempts, per minutes]
    'rate_limits' => [
        'posts'    => [5, 10],
        'comments' => [20, 10],
        'likes'    => [60, 1],
        'reports'  => [10, 60],
    ],

    // Soft-deleted posts, comments and their photos are removed for good after this many days.
    'purge_after_days' => 30,
];
