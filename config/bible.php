<?php

/*
|--------------------------------------------------------------------------
| Bible text for devotion verses (King James Version, public domain)
|--------------------------------------------------------------------------
| Fixed here, not in .env. `php artisan bible:import-kjv` refuses any text whose
| UNZIPPED sha256 differs from the one below. See database/data/kjv/README.md.
*/

return [

    'kjv' => [
        'file'   => database_path('data/kjv/eng-kjv2006_vpl.txt.gz'),
        'sha256' => '68c5f764bf1c204868c3eb72592035c0947f057b608c2149dbff765d8ddd86f6',
    ],

];
