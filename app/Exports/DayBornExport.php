<?php

namespace App\Exports;

use App\Support\DayBorn\Report;
use App\Support\DayBorn\Weekdays;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Day-born offering for a year as .xlsx (PhpSpreadsheet, like FinanceExport):
 * sheet "Weeks" (one row per published week, the 7 groups, total, winner) and
 * sheet "Summary" (each group's total and weeks won).
 */
class DayBornExport
{
    public function __construct(private readonly Report $report)
    {
    }

    public function filename(): string
    {
        return "day-born-offering-{$this->report->year}.xlsx";
    }

    /** Writes the workbook to php://output (call inside a streamed response). */
    public function write(): void
    {
        $book = new Spreadsheet();

        $weeks = $book->getActiveSheet();
        $weeks->setTitle('Weeks');
        $header = ['Service date', 'Special service'];
        foreach (Weekdays::ALL as $group) {
            $header[] = "{$group['day']} ({$group['male']}/{$group['female']})";
        }
        array_push($header, 'Total (GHS)', 'Winner');
        $weeks->fromArray($header, null, 'A1');

        $row = 2;
        foreach ($this->report->weeks as $week) {
            $amounts = $week->amounts();
            $winners = collect($week->ranking())->where('position', 1)->pluck('weekday')->map(fn ($d) => Weekdays::day($d))->implode(', ');
            $line = [$week->service_date->toDateString(), $week->special_service ? 'Yes' : 'No'];
            foreach (Weekdays::numbers() as $weekday) {
                $line[] = (float) $amounts[$weekday];
            }
            array_push($line, (float) $week->total(), $week->total() === '0.00' ? '' : $winners);
            $weeks->fromArray($line, null, "A{$row}");
            $row++;
        }
        $this->style($weeks, count($header), $row - 1, 3, 10);

        $summary = $book->createSheet();
        $summary->setTitle('Summary');
        $summary->fromArray(['Place', 'Day-born group', 'Total (GHS)', 'Weeks won'], null, 'A1');
        $row = 2;
        foreach ($this->report->groups as $group) {
            $summary->fromArray([$group['position'], Weekdays::label($group['weekday']), (float) $group['total'], $group['weeks_won']], null, "A{$row}");
            $row++;
        }
        $summary->fromArray(['', 'Total', (float) $this->report->total, $this->report->weeks->count() . ' weeks'], null, "A{$row}");
        $this->style($summary, 4, $row, 3, 3);

        (new Xlsx($book))->save('php://output');
    }

    private function style($sheet, int $columns, int $lastRow, int $firstMoneyColumn, int $lastMoneyColumn): void
    {
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns);
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        for ($c = 1; $c <= $columns; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        if ($lastRow >= 2) {
            $from = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstMoneyColumn);
            $to = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastMoneyColumn);
            $sheet->getStyle("{$from}2:{$to}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }
}
