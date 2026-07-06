<?php

namespace App\Exports;

use App\Models\Transaction;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FinanceExport
{
    protected int $year;
    protected string $churchName;
    protected string $branch;
    protected string $location;

    // Subcategory groupings
    private array $b1Subcats = [
        'Funeral Dept', 'Transport (Bus)', 'Wednesday Prayer',
        'Welfare', 'Women Ministry', 'Scholarship & Needy',
    ];

    private array $b2Subcats = [
        'General Expense', "Rt. Pastor's Pension Payments",
        'Salaries & Staff Allowance', 'SSNIT/2nd Tier/PAYE', 'Travel & Transport',
    ];

    private array $b3Subcats = [
        'Cleaning & Sanitation', 'Gen. Coun/Tithe on Tithe', 'Donation (Expense)',
        'Internet & Comm Cost', 'Medicals', 'Refreshments', 'Repairs & Maintenance',
        'Retreat/Revival/Seminar', 'Printing & Stationery', 'Utility Bills',
        'School Fees', 'Security & Police on Duty', 'Satellite Church',
    ];

    public function __construct(int $year = null)
    {
        $this->year       = $year ?? now()->year;
        $this->churchName = 'GRACEWORLD INTERNATIONAL';
        $this->branch     = 'MAIN BRANCH';
        $this->location   = 'KUMASI';
    }

    public function download()
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $this->buildIESheet($spreadsheet);
        $this->buildIncomeNoteSheet($spreadsheet);
        $this->buildExpenditureNoteSheet($spreadsheet);
        $this->buildActualsSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndex(0);
        $writer   = new Xlsx($spreadsheet);
        $filename = 'Rhema_Finance_' . $this->year . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }

    // ── HELPERS ───────────────────────────────────────────

    private function getMonthlyAmount(string $type, int $month): float
    {
        return Transaction::where('type', $type)
            ->where('category', 'Income')
            ->whereYear('transaction_date', $this->year)
            ->whereMonth('transaction_date', $month)
            ->where('status', 'Confirmed')
            ->sum('amount');
    }

    private function getMonthlyBySubcategory(string $subcategory, int $month): float
    {
        return Transaction::where('subcategory', $subcategory)
            ->whereYear('transaction_date', $this->year)
            ->whereMonth('transaction_date', $month)
            ->where('status', 'Confirmed')
            ->sum('amount');
    }

    private function getMonthlyBySubcategoryGroup(array $subcategories, int $month): float
    {
        return Transaction::whereIn('subcategory', $subcategories)
            ->whereYear('transaction_date', $this->year)
            ->whereMonth('transaction_date', $month)
            ->where('status', 'Confirmed')
            ->sum('amount');
    }

    private function getAllIncome(int $month): float
    {
        return Transaction::where('category', 'Income')
            ->whereYear('transaction_date', $this->year)
            ->whereMonth('transaction_date', $month)
            ->where('status', 'Confirmed')
            ->sum('amount');
    }

    private function getAllExpense(int $month): float
    {
        return Transaction::where('category', 'Expense')
            ->whereYear('transaction_date', $this->year)
            ->whereMonth('transaction_date', $month)
            ->where('status', 'Confirmed')
            ->sum('amount');
    }

    private function styleHeader($sheet, string $range, string $bg = '1a3c5e', string $fg = 'FFFFFF'): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => $fg], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
    }

    private function styleRow($sheet, string $range, string $bg = 'FFFFFF'): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);
    }

    private function styleTotalRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'    => ['bold' => true, 'color' => ['rgb' => '1a3c5e']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'dbeafe']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '93C5FD']]],
        ]);
    }

    private function setCurrency($sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    private function months(): array
    {
        return [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];
    }

    private function setColWidths($sheet, int $labelWidth = 35): void
    {
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth($labelWidth);
        $sheet->getColumnDimension('C')->setWidth(8);
        foreach (range('D', 'P') as $c) {
            $sheet->getColumnDimension($c)->setWidth(13);
        }
    }

    private function writeMonthlyRow($sheet, int $row, array $monthlyAmounts): array
    {
        $col   = 'D';
        $total = 0;
        foreach (range(1, 12) as $m) {
            $amt = $monthlyAmounts[$m] ?? 0;
            $sheet->setCellValue($col . $row, $amt ?: '-');
            $total += $amt;
            $col++;
        }
        $sheet->setCellValue($col . $row, $total ?: '-');
        return ['lastCol' => $col, 'total' => $total];
    }

    // ── SHEET 1 — IE SUMMARY ──────────────────────────────

    private function buildIESheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('IE');
        $months = $this->months();

        // Header
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A1', $this->churchName);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1a3c5e']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells('A2:P2');
        $sheet->setCellValue('A2', '- ' . $this->branch);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:P3');
        $sheet->setCellValue('A3', $this->location);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A4:P4');
        $sheet->setCellValue('A4', 'INCOME & EXPENDITURE STATEMENT — YEAR ' . $this->year);
        $sheet->getStyle('A4')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a3c5e']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Column headers
        $sheet->setCellValue('A6', '#');
        $sheet->setCellValue('B6', 'PARTICULARS');
        $sheet->setCellValue('C6', 'REF');
        $col = 'D';
        foreach ($months as $m) {
            $sheet->setCellValue($col . '6', strtoupper($m));
            $col++;
        }
        $sheet->setCellValue($col . '6', 'TOTAL');
        $this->styleHeader($sheet, 'A6:' . $col . '6');
        $sheet->getRowDimension(6)->setRowHeight(25);

        // ── SECTION A — INCOME ──
        $row = 7;
        $sheet->mergeCells('A' . $row . ':P' . $row);
        $sheet->setCellValue('A' . $row, 'A   INCOME — INFLOWS');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '15803d']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'dcfce7']],
        ]);
        $row++;

        $incomeItems = [
            ['1', 'General Offering',       'A1', 'Offering'],
            ['2', 'Tithe',                  'A2', 'Tithe'],
            ['3', 'Harvest Proceeds',        'A3', 'First Fruit'],
            ['4', 'Departmental Proceeds',   'A4', 'Donation'],
            ['5', 'Project Offering',        'A5', 'Pledge'],
            ['6', 'Seed Sowing',             'A6', 'Seed'],
            ['7', 'Others',                  'A7', 'Other'],
        ];

        $grandIncome = 0;
        foreach ($incomeItems as $item) {
            $sheet->setCellValue('A' . $row, $item[0]);
            $sheet->setCellValue('B' . $row, $item[1]);
            $sheet->setCellValue('C' . $row, $item[2]);
            $amounts = [];
            foreach (range(1, 12) as $m) {
                $amounts[$m] = $this->getMonthlyAmount($item[3], $m);
            }
            $result = $this->writeMonthlyRow($sheet, $row, $amounts);
            $this->styleRow($sheet, 'A' . $row . ':' . $result['lastCol'] . $row, $row % 2 === 0 ? 'f8fafc' : 'FFFFFF');
            $this->setCurrency($sheet, 'D' . $row . ':' . $result['lastCol'] . $row);
            $row++;
        }

        // Total Income
        $sheet->setCellValue('B' . $row, 'TOTAL INCOME');
        $col = 'D';
        foreach (range(1, 12) as $m) {
            $amt = $this->getAllIncome($m);
            $sheet->setCellValue($col . $row, $amt ?: '-');
            $grandIncome += $amt;
            $col++;
        }
        $sheet->setCellValue($col . $row, $grandIncome ?: '-');
        $this->styleTotalRow($sheet, 'A' . $row . ':' . $col . $row);
        $this->setCurrency($sheet, 'D' . $row . ':' . $col . $row);
        $row += 2;

        // ── SECTION B — EXPENDITURE ──
        $sheet->mergeCells('A' . $row . ':P' . $row);
        $sheet->setCellValue('A' . $row, 'B   EXPENDITURE — OUTFLOWS');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'b91c1c']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fee2e2']],
        ]);
        $row++;

        $expItems = [
            ['1', 'Department Expenses', 'B1', $this->b1Subcats],
            ['2', 'Administration',      'B2', $this->b2Subcats],
            ['3', 'Other Expenses',      'B3', $this->b3Subcats],
        ];

        $grandExpense = 0;
        foreach ($expItems as $item) {
            $sheet->setCellValue('A' . $row, $item[0]);
            $sheet->setCellValue('B' . $row, $item[1]);
            $sheet->setCellValue('C' . $row, $item[2]);
            $col = 'D';
            $rowTotal = 0;
            foreach (range(1, 12) as $m) {
                $amt = $this->getMonthlyBySubcategoryGroup($item[3], $m);
                $sheet->setCellValue($col . $row, $amt ?: '-');
                $rowTotal += $amt;
                $col++;
            }
            $sheet->setCellValue($col . $row, $rowTotal ?: '-');
            $grandExpense += $rowTotal;
            $this->styleRow($sheet, 'A' . $row . ':' . $col . $row, $row % 2 === 0 ? 'f8fafc' : 'FFFFFF');
            $this->setCurrency($sheet, 'D' . $row . ':' . $col . $row);
            $row++;
        }

        // Total Expenditure
        $sheet->setCellValue('B' . $row, 'TOTAL EXPENDITURE');
        $col = 'D';
        $totalExp = 0;
        foreach (range(1, 12) as $m) {
            $amt = $this->getAllExpense($m);
            $sheet->setCellValue($col . $row, $amt ?: '-');
            $totalExp += $amt;
            $col++;
        }
        $sheet->setCellValue($col . $row, $totalExp ?: '-');
        $this->styleTotalRow($sheet, 'A' . $row . ':' . $col . $row);
        $this->setCurrency($sheet, 'D' . $row . ':' . $col . $row);
        $row += 2;

        // ── SECTION C — BALANCE ──
        $sheet->setCellValue('A' . $row, 'C');
        $sheet->setCellValue('B' . $row, 'INFLOWS — OUTFLOWS = BALANCE');
        $col = 'D';
        foreach (range(1, 12) as $m) {
            $bal = $this->getAllIncome($m) - $this->getAllExpense($m);
            $sheet->setCellValue($col . $row, $bal ?: '-');
            $col++;
        }
        $sheet->setCellValue($col . $row, ($grandIncome - $totalExp) ?: '-');
        $sheet->getStyle('A' . $row . ':' . $col . $row)->applyFromArray([
            'font'    => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a3c5e']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
        $this->setCurrency($sheet, 'D' . $row . ':' . $col . $row);

        $this->setColWidths($sheet, 30);
    }

    // ── SHEET 2 — INCOME NOTE ─────────────────────────────

    private function buildIncomeNoteSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('INCOME NOTE');
        $months = $this->months();

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'INCOME NOTES — YEAR ' . $this->year);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15803d']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', 'REF');
        $sheet->setCellValue('B2', 'NOTES');
        $col = 'C';
        foreach ($months as $m) {
            $sheet->setCellValue($col . '2', strtoupper($m));
            $col++;
        }
        $sheet->setCellValue($col . '2', 'TOTAL');
        $this->styleHeader($sheet, 'A2:' . $col . '2', '15803d');

        $row = 3;

        // Overall Total
        $sheet->setCellValue('B' . $row, 'OVERALL TOTAL');
        $col     = 'C';
        $overall = 0;
        foreach (range(1, 12) as $m) {
            $amt = $this->getAllIncome($m);
            $sheet->setCellValue($col . $row, $amt ?: '-');
            $overall += $amt;
            $col++;
        }
        $sheet->setCellValue($col . $row, $overall ?: '-');
        $this->styleTotalRow($sheet, 'A' . $row . ':' . $col . $row);
        $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
        $row += 2;

        $sections = [
            'A1' => [
                'title' => 'A1 — General Offering',
                'type'  => 'Offering',
                'items' => [
                    ['i',   'Executive (English) Service', 'Executive (English) Service'],
                    ['ii',  'Divine (Twi) Service',        'Divine (Twi) Service'],
                    ['iii', 'Joint Service',               'Joint Service'],
                    ['iv',  'Bible Studies - Tuesday',     'Bible Studies - Tuesday'],
                    ['v',   'Miracle Service - Friday',    'Miracle Service - Friday'],
                    ['vi',  'Fundraisings',                'Fundraisings'],
                ],
            ],
            'A2' => [
                'title' => 'A2 — Tithe',
                'type'  => 'Tithe',
                'items' => [['', 'Tithe', null]],
            ],
            'A3' => [
                'title' => 'A3 — Harvest Proceeds',
                'type'  => 'First Fruit',
                'items' => [['', 'Harvest Proceeds', null]],
            ],
            'A4' => [
                'title' => 'A4 — Department Fund (Ministry)',
                'type'  => 'Donation',
                'items' => [
                    ['i',    'Men Ministry',            'Men Ministry'],
                    ['ii',   'Women Ministry',          'Women Ministry'],
                    ['iv',   'Children Ministry',       'Children Ministry'],
                    ['v',    'Sunday School',           'Sunday School'],
                    ['vi',   'Funeral Dept.',           'Funeral Dept.'],
                    ['vii',  'Christ Ambassador (CA)', 'Christ Ambassador (CA)'],
                    ['viii', 'Welfare Dept.',          'Welfare Dept.'],
                    ['ix',   'Prayer Mtg (Wednesday)', 'Prayer Mtg (Wednesday)'],
                ],
            ],
            'A5' => [
                'title' => 'A5 — Project Offering',
                'type'  => 'Pledge',
                'items' => [['', 'Project Offering', null]],
            ],
            'A6' => [
                'title' => 'A6 — Seed Sowing',
                'type'  => 'Seed',
                'items' => [['', 'Seed Sowing', null]],
            ],
            'A7' => [
                'title' => 'A7 — Other Income',
                'type'  => 'Other',
                'items' => [
                    ['i',    'Dist/Reg/Gen. Council',      'Dist/Reg/Gen. Council'],
                    ['ii',   'Fund Raising',               'Fund Raising'],
                    ['iii',  'Child Dedication',           'Child Dedication'],
                    ['iv',   'All Night',                  'All Night'],
                    ['v',    'Satellite Churches',         'Satellite Churches'],
                    ['vi',   'Revival/Retreat/Seminars',   'Revival/Retreat/Seminars'],
                    ['vii',  'Scholarship Fund',           'Scholarship Fund'],
                    ['viii', 'Book Sales (Sunday School)', 'Book Sales (Sunday School)'],
                    ['ix',   'Missions',                   'Missions'],
                    ['x',    'Joy Fellowship',             'Joy Fellowship'],
                    ['xi',   'Interest Received',          'Interest Received'],
                ],
            ],
        ];

        foreach ($sections as $ref => $section) {
            $sheet->mergeCells('A' . $row . ':N' . $row);
            $sheet->setCellValue('A' . $row, $section['title']);
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '15803d']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'dcfce7']],
            ]);
            $row++;

            $subtotals = array_fill(1, 12, 0);
            $secTotal  = 0;

            foreach ($section['items'] as $item) {
                $sheet->setCellValue('A' . $row, $item[0]);
                $sheet->setCellValue('B' . $row, $item[1]);
                $col   = 'C';
                $total = 0;
                foreach (range(1, 12) as $m) {
                    $amt = $item[2] === null
                        ? $this->getMonthlyAmount($section['type'], $m)
                        : $this->getMonthlyBySubcategory($item[2], $m);
                    $sheet->setCellValue($col . $row, $amt ?: '-');
                    $total += $amt;
                    $subtotals[$m] += $amt;
                    $col++;
                }
                $sheet->setCellValue($col . $row, $total ?: '-');
                $secTotal += $total;
                $this->styleRow($sheet, 'A' . $row . ':' . $col . $row, $row % 2 === 0 ? 'f8fafc' : 'FFFFFF');
                $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
                $row++;
            }

            $sheet->setCellValue('B' . $row, 'SUBTOTAL — ' . $ref);
            $col = 'C';
            foreach (range(1, 12) as $m) {
                $sheet->setCellValue($col . $row, $subtotals[$m] ?: '-');
                $col++;
            }
            $sheet->setCellValue($col . $row, $secTotal ?: '-');
            $this->styleTotalRow($sheet, 'A' . $row . ':' . $col . $row);
            $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
            $row += 2;
        }

        $this->setColWidths($sheet);
    }

    // ── SHEET 3 — EXPENDITURE NOTE ────────────────────────

    private function buildExpenditureNoteSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('EXPENDITURE NOTE');
        $months = $this->months();

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'EXPENDITURE NOTES — YEAR ' . $this->year);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'b91c1c']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', 'REF');
        $sheet->setCellValue('B2', 'NOTES');
        $col = 'C';
        foreach ($months as $m) {
            $sheet->setCellValue($col . '2', strtoupper($m));
            $col++;
        }
        $sheet->setCellValue($col . '2', 'TOTAL');
        $this->styleHeader($sheet, 'A2:' . $col . '2', 'b91c1c');

        $row = 3;

        $sections = [
            'B1' => [
                'title' => 'B1 — Department Expenses',
                'items' => [
                    ['i',   'Funeral Dept',        'Funeral Dept'],
                    ['ii',  'Transport (Bus)',      'Transport (Bus)'],
                    ['iii', 'Wednesday Prayer',     'Wednesday Prayer'],
                    ['iv',  'Welfare',              'Welfare'],
                    ['v',   'Women Ministry',       'Women Ministry'],
                    ['vi',  'Scholarship & Needy',  'Scholarship & Needy'],
                ],
            ],
            'B2' => [
                'title' => 'B2 — Administration Expenses',
                'items' => [
                    ['i',   'General Expense',                   'General Expense'],
                    ['ii',  "Rt. Pastor's Pension Payments",     "Rt. Pastor's Pension Payments"],
                    ['iii', 'Salaries & Staff Allowance',        'Salaries & Staff Allowance'],
                    ['iv',  'SSNIT/2nd Tier/PAYE',               'SSNIT/2nd Tier/PAYE'],
                    ['v',   'Travel & Transport',                'Travel & Transport'],
                ],
            ],
            'B3' => [
                'title' => 'B3 — Other Expenses',
                'items' => [
                    ['i',    'Cleaning & Sanitation',      'Cleaning & Sanitation'],
                    ['ii',   'Gen. Coun/Tithe on Tithe',   'Gen. Coun/Tithe on Tithe'],
                    ['iii',  'Donation (Expense)',          'Donation (Expense)'],
                    ['iv',   'Internet & Comm Cost',        'Internet & Comm Cost'],
                    ['v',    'Medicals',                    'Medicals'],
                    ['vi',   'Refreshments',                'Refreshments'],
                    ['vii',  'Repairs & Maintenance',       'Repairs & Maintenance'],
                    ['viii', 'Retreat/Revival/Seminar',     'Retreat/Revival/Seminar'],
                    ['ix',   'Printing & Stationery',       'Printing & Stationery'],
                    ['x',    'Utility Bills',               'Utility Bills'],
                    ['xi',   'School Fees',                 'School Fees'],
                    ['xii',  'Security & Police on Duty',   'Security & Police on Duty'],
                    ['xiii', 'Satellite Church',            'Satellite Church'],
                ],
            ],
        ];

        foreach ($sections as $ref => $section) {
            $sheet->mergeCells('A' . $row . ':N' . $row);
            $sheet->setCellValue('A' . $row, $section['title']);
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'b91c1c']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fee2e2']],
            ]);
            $row++;

            $subtotals = array_fill(1, 12, 0);
            $secTotal  = 0;

            foreach ($section['items'] as $item) {
                $sheet->setCellValue('A' . $row, $item[0]);
                $sheet->setCellValue('B' . $row, $item[1]);
                $col   = 'C';
                $total = 0;
                foreach (range(1, 12) as $m) {
                    $amt = $this->getMonthlyBySubcategory($item[2], $m);
                    $sheet->setCellValue($col . $row, $amt ?: '-');
                    $total += $amt;
                    $subtotals[$m] += $amt;
                    $col++;
                }
                $sheet->setCellValue($col . $row, $total ?: '-');
                $secTotal += $total;
                $this->styleRow($sheet, 'A' . $row . ':' . $col . $row, $row % 2 === 0 ? 'f8fafc' : 'FFFFFF');
                $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
                $row++;
            }

            $sheet->setCellValue('B' . $row, 'SUBTOTAL — ' . $ref);
            $col = 'C';
            foreach (range(1, 12) as $m) {
                $sheet->setCellValue($col . $row, $subtotals[$m] ?: '-');
                $col++;
            }
            $sheet->setCellValue($col . $row, $secTotal ?: '-');
            $this->styleTotalRow($sheet, 'A' . $row . ':' . $col . $row);
            $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
            $row += 2;
        }

        $this->setColWidths($sheet);
    }

    // ── SHEET 4 — ACTUALS ─────────────────────────────────

    private function buildActualsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('ACTUALS');
        $months = $this->months();

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'ACTUALS — YEAR ' . $this->year);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7c3aed']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', '#');
        $sheet->setCellValue('B2', 'PARTICULARS');
        $col = 'C';
        foreach ($months as $m) {
            $sheet->setCellValue($col . '2', strtoupper($m));
            $col++;
        }
        $sheet->setCellValue($col . '2', 'TOTAL');
        $this->styleHeader($sheet, 'A2:' . $col . '2', '7c3aed');

        $row = 3;

        // Section A
        $sheet->mergeCells('A' . $row . ':N' . $row);
        $sheet->setCellValue('A' . $row, 'A   INCOME — INFLOWS');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '15803d']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'dcfce7']],
        ]);
        $row++;

        $incomeRows = [
            ['1', 'General Offertory', 'Offering'],
            ['2', 'Tithe',             'Tithe'],
            ['3', 'Seed Sowing',       'Seed'],
            ['4', 'Others',            'Other'],
        ];

        foreach ($incomeRows as $item) {
            $sheet->setCellValue('A' . $row, $item[0]);
            $sheet->setCellValue('B' . $row, $item[1]);
            $col   = 'C';
            $total = 0;
            foreach (range(1, 12) as $m) {
                $amt = $this->getMonthlyAmount($item[2], $m);
                $sheet->setCellValue($col . $row, $amt ?: '-');
                $total += $amt;
                $col++;
            }
            $sheet->setCellValue($col . $row, $total ?: '-');
            $this->styleRow($sheet, 'A' . $row . ':' . $col . $row, $row % 2 === 0 ? 'f8fafc' : 'FFFFFF');
            $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
            $row++;
        }

        // Total Income
        $sheet->setCellValue('B' . $row, 'TOTAL INCOME');
        $col         = 'C';
        $grandIncome = 0;
        foreach (range(1, 12) as $m) {
            $amt = $this->getAllIncome($m);
            $sheet->setCellValue($col . $row, $amt ?: '-');
            $grandIncome += $amt;
            $col++;
        }
        $sheet->setCellValue($col . $row, $grandIncome ?: '-');
        $this->styleTotalRow($sheet, 'A' . $row . ':' . $col . $row);
        $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
        $row += 2;

        // Section B
        $sheet->mergeCells('A' . $row . ':N' . $row);
        $sheet->setCellValue('A' . $row, 'B   EXPENDITURE — OUTFLOWS');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'b91c1c']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fee2e2']],
        ]);
        $row++;

        $expRows = [
            ['1', 'Department Expenses', $this->b1Subcats],
            ['2', 'Administration',      $this->b2Subcats],
            ['3', 'Other Expenses',      $this->b3Subcats],
        ];

        foreach ($expRows as $item) {
            $sheet->setCellValue('A' . $row, $item[0]);
            $sheet->setCellValue('B' . $row, $item[1]);
            $col      = 'C';
            $rowTotal = 0;
            foreach (range(1, 12) as $m) {
                $amt = $this->getMonthlyBySubcategoryGroup($item[2], $m);
                $sheet->setCellValue($col . $row, $amt ?: '-');
                $rowTotal += $amt;
                $col++;
            }
            $sheet->setCellValue($col . $row, $rowTotal ?: '-');
            $this->styleRow($sheet, 'A' . $row . ':' . $col . $row, $row % 2 === 0 ? 'f8fafc' : 'FFFFFF');
            $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
            $row++;
        }

        // Total Expenditure
        $sheet->setCellValue('B' . $row, 'TOTAL EXPENDITURE');
        $col          = 'C';
        $grandExpense = 0;
        foreach (range(1, 12) as $m) {
            $amt = $this->getAllExpense($m);
            $sheet->setCellValue($col . $row, $amt ?: '-');
            $grandExpense += $amt;
            $col++;
        }
        $sheet->setCellValue($col . $row, $grandExpense ?: '-');
        $this->styleTotalRow($sheet, 'A' . $row . ':' . $col . $row);
        $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);
        $row += 2;

        // Balance
        $sheet->setCellValue('A' . $row, 'C');
        $sheet->setCellValue('B' . $row, 'BALANCE (Income - Expenditure)');
        $col = 'C';
        foreach (range(1, 12) as $m) {
            $bal = $this->getAllIncome($m) - $this->getAllExpense($m);
            $sheet->setCellValue($col . $row, $bal ?: '-');
            $col++;
        }
        $sheet->setCellValue($col . $row, ($grandIncome - $grandExpense) ?: '-');
        $sheet->getStyle('A' . $row . ':' . $col . $row)->applyFromArray([
            'font'    => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7c3aed']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
        $this->setCurrency($sheet, 'C' . $row . ':' . $col . $row);

        $this->setColWidths($sheet);
    }
}