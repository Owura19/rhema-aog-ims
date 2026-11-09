<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS driver
    |--------------------------------------------------------------------------
    | 'log'  — development: messages are written to the log, no phone receives them.
    |          In production the app refuses to send codes through it (503 sms_unavailable).
    | 'fake' — tests only (forced by phpunit.xml): messages are recorded in memory.
    | A real provider (Hubtel / Arkesel / mNotify) will be added as another driver.
    */

    'driver' => env('SMS_DRIVER', 'log'),

    /*
    | Sender name shown on members' phones (for the real provider, once chosen).
    */

    'sender_id' => env('SMS_SENDER_ID', 'RhemaAoG'),

];
