<?php

namespace App\Contracts;

/**
 * Sends SMS messages. Bound in AppServiceProvider from config('sms.driver').
 * Drivers today: 'log' (development) and 'fake' (tests). A real provider
 * (Hubtel / Arkesel / mNotify) is a later drop-in implementing this interface.
 */
interface SmsSender
{
    /** @param string $to normalised +233XXXXXXXXX */
    public function send(string $to, string $message): void;

    /**
     * False for drivers that never reach a real phone (the log driver).
     * Production refuses to "send" codes through such a driver (503 sms_unavailable).
     */
    public function deliversToRealPhones(): bool;
}
