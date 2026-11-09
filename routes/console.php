<?php

use App\Models\PhoneVerificationCode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks — needs the server cron:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|--------------------------------------------------------------------------
*/

// Delete mobile app tokens that expired more than 24 hours ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Delete SMS verification codes older than a day (see PhoneVerificationCode::prunable()).
Schedule::command('model:prune', ['--model' => [PhoneVerificationCode::class]])->daily();
