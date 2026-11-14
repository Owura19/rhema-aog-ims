<?php

namespace App\Support\DayBorn;

/**
 * The 7 day-born groups, 0 = Sunday ... 6 = Saturday (PHP/Carbon dayOfWeek order),
 * with the Akan day names (male / female).
 */
final class Weekdays
{
    public const ALL = [
        0 => ['day' => 'Sunday',    'male' => 'Kwasi',  'female' => 'Akosua'],
        1 => ['day' => 'Monday',    'male' => 'Kwadwo', 'female' => 'Adwoa'],
        2 => ['day' => 'Tuesday',   'male' => 'Kwabena', 'female' => 'Abenaa'],
        3 => ['day' => 'Wednesday', 'male' => 'Kwaku',  'female' => 'Akua'],
        4 => ['day' => 'Thursday',  'male' => 'Yaw',    'female' => 'Yaa'],
        5 => ['day' => 'Friday',    'male' => 'Kofi',   'female' => 'Afua'],
        6 => ['day' => 'Saturday',  'male' => 'Kwame',  'female' => 'Amma'],
    ];

    public static function day(int $weekday): string
    {
        return self::ALL[$weekday]['day'];
    }

    /** "Kofi/Afua" */
    public static function akan(int $weekday): string
    {
        return self::ALL[$weekday]['male'] . '/' . self::ALL[$weekday]['female'];
    }

    /** "Friday (Kofi/Afua)" */
    public static function label(int $weekday): string
    {
        return self::day($weekday) . ' (' . self::akan($weekday) . ')';
    }

    /** @return list<int> */
    public static function numbers(): array
    {
        return array_keys(self::ALL);
    }
}
