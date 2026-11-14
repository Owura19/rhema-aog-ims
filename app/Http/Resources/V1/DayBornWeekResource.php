<?php

namespace App\Http\Resources\V1;

use App\Models\DayBornWeek;
use App\Support\DayBorn\Weekdays;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published day-born week for the app: date, special service, total and the 7 groups
 * ranked (ties share a position: 1, 1, 3). Never notes, who recorded it or finance links.
 * Expects offerings to be loaded.
 *
 * @mixin DayBornWeek
 */
class DayBornWeekResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'service_date'    => $this->service_date->toDateString(),
            'special_service' => $this->special_service,
            'total'           => $this->total(),
            'groups'          => array_map(fn (array $row) => [
                'position' => $row['position'],
                'weekday'  => Weekdays::day($row['weekday']),
                'akan'     => [
                    'male'   => Weekdays::ALL[$row['weekday']]['male'],
                    'female' => Weekdays::ALL[$row['weekday']]['female'],
                ],
                'label'    => Weekdays::akan($row['weekday']),
                'amount'   => $row['amount'],
            ], $this->ranking()),
        ];
    }
}
