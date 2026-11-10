<?php

/*
|--------------------------------------------------------------------------
| Weekly church programmes
|--------------------------------------------------------------------------
| `php artisan programs:generate-services` (scheduled daily at 01:00) turns
| each active programme into real church_services rows up to 8 weeks ahead,
| so they appear on the app calendar and can be used for attendance.
|
| Ships EMPTY on purpose: nothing appears until the church office provides
| the real programmes.
|
|   After editing this file on the server, run `php artisan optimize`
|   (config is cached in production), or the generator keeps using the old list.
|
| Each programme:
|   key          stable slug (a-z, 0-9, hyphens; max 64). NEVER rename it:
|                a renamed key is treated as a NEW programme and its dates
|                are generated again.
|   name         the service name shown to members and staff
|   service_type one of: Sunday Service, Midweek Service, Prayer Meeting,
|                Special Event, Convention, Other
|   day          monday … sunday (lower case)
|   start_time   HH:MM, 24-hour
|   end_time     HH:MM or null (must be after start_time)
|   venue        text or null
|   active       true/false (optional, default true) — false stops new dates
|
| The generator never edits, cancels or deletes a service, and never
| recreates a date it has handled before — even if staff deleted it.
*/

return [

    'programs' => [

        // Example (NOT live — copy, uncomment and fill in):
        // [
        //     'key'          => 'midweek-bible-study',
        //     'name'         => 'Midweek Bible Study',
        //     'service_type' => 'Midweek Service',
        //     'day'          => 'wednesday',
        //     'start_time'   => '18:30',
        //     'end_time'     => '20:00',
        //     'venue'        => 'Main Auditorium',
        //     'active'       => true,
        // ],

    ],

];
