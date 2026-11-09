<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes the SMS to the application log instead of sending it.
 * It reaches no phone — so production never uses it to deliver codes.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS not sent (log driver)', ['to' => $to, 'message' => $message]);
    }

    public function deliversToRealPhones(): bool
    {
        return false;
    }
}
