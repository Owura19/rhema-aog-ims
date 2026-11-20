<?php

namespace App\Exports;

use App\Models\Loan;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * One loan as .xlsx (PhpSpreadsheet, like DayBornExport): "Summary", "Repayments" (each counted
 * transaction), "Monthly" (repaid per month) and "Adjustments" (manual changes and reasons).
 */
class LoanExport
{
    public function __construct(private readonly Loan $loan, private readonly float $ledgerBalance)
    {
    }

    public function filename(): string
    {
        return 'loan-' . \Illuminate\Support\Str::slug($this->loan->lender) . '-' . now()->toDateString() . '.xlsx';
    }

    /** Writes the workbook to php://output (call inside a streamed response). */
    public function write(): void
    {
        $loan = $this->loan->loadMissing(['liabilityAccount', 'repaymentAccount']);
        $repayments = $loan->repayments()->with(['transaction', 'cashAccount'])->get()
            ->sortBy(fn ($r) => [$r->transaction?->transaction_date?->toDateString(), $r->transaction_id])->values();
        $book = new Spreadsheet();

        $summary = $book->getActiveSheet();
        $summary->setTitle('Summary');
        $rows = [
            ['Lender', $loan->lender],
            ['Description', (string) $loan->description],
            ['Status', $loan->status . ($loan->paid_off_at ? ' on ' . $loan->paid_off_at->toDateString() : '')],
            ['Opening balance (GHS)', (float) $loan->opening_balance],
            ['Opening date', $loan->opening_date->toDateString()],
            ['Repayments counted from', $loan->repayment_start_date->toDateString()],
            ['Counted types', implode(', ', $loan->counted_types)],
            ['Repayments are paid from', $loan->repayment_source === Loan::SOURCE_FIXED
                ? ($loan->repaymentAccount?->code . ' ' . $loan->repaymentAccount?->name)
                : 'The account each payment was received into'],
            ['Owed incl. corrections (GHS)', $loan->owedTotal()],
            ['Repaid to date (GHS)', $loan->repaidTotal()],
            ['Balance remaining (GHS)', $loan->balance()],
            ['Surplus not applied (GHS)', $loan->surplus()],
            ['Ledger balance, ' . $loan->liabilityAccount?->code . ' (GHS)', $this->ledgerBalance],
            ['Exported', now()->toDateTimeString()],
        ];
        $summary->fromArray($rows, null, 'A1');
        $summary->getStyle('A1:A' . count($rows))->getFont()->setBold(true);
        $summary->getColumnDimension('A')->setAutoSize(true);
        $summary->getColumnDimension('B')->setAutoSize(true);
        foreach ([4, 9, 10, 11, 12, 13] as $r) {
            $summary->getStyle("B{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $sheet = $book->createSheet();
        $sheet->setTitle('Repayments');
        $sheet->fromArray(['Transaction', 'Date', 'Type', 'Payer', 'Transaction amount', 'Applied to loan', 'Surplus', 'Paid from', 'Note'], null, 'A1');
        $row = 2;
        foreach ($repayments as $r) {
            $tx = $r->transaction;
            $sheet->fromArray([
                $tx?->reference, $tx?->transaction_date?->toDateString(), $tx?->type, $tx?->payer_label,
                (float) $r->transaction_amount, (float) $r->amount_applied, (float) $r->surplus,
                $r->cashAccount ? "{$r->cashAccount->code} {$r->cashAccount->name}" : '',
                $r->flag ? 'Transaction not in the ledger' : '',
            ], null, "A{$row}");
            $row++;
        }
        $sheet->fromArray(['Total', '', '', '', (float) $repayments->sum('transaction_amount'), (float) $repayments->sum('amount_applied'), (float) $repayments->sum('surplus')], null, "A{$row}");
        $this->style($sheet, 9, $row, 5, 7);

        $monthly = $book->createSheet();
        $monthly->setTitle('Monthly');
        $monthly->fromArray(['Month', 'Payments', 'Applied to loan (GHS)'], null, 'A1');
        $row = 2;
        foreach ($repayments->groupBy(fn ($r) => $r->transaction?->transaction_date?->format('Y-m'))->sortKeys() as $month => $group) {
            $monthly->fromArray([$month, $group->count(), (float) $group->sum('amount_applied')], null, "A{$row}");
            $row++;
        }
        $this->style($monthly, 3, $row - 1, 3, 3);

        $adj = $book->createSheet();
        $adj->setTitle('Adjustments');
        $adj->fromArray(['Date', 'Kind', 'Amount (GHS, + owed more / − less)', 'Account', 'Reason', 'By', 'Recorded'], null, 'A1');
        $row = 2;
        foreach ($loan->adjustments()->with(['counterAccount', 'createdBy'])->orderBy('adjustment_date')->get() as $a) {
            $adj->fromArray([
                $a->adjustment_date->toDateString(), $a->kind === 'repayment' ? 'Repayment (manual)' : 'Correction', (float) $a->amount,
                "{$a->counterAccount?->code} {$a->counterAccount?->name}", $a->reason, $a->createdBy?->name, $a->created_at?->toDateTimeString(),
            ], null, "A{$row}");
            $row++;
        }
        $this->style($adj, 7, $row - 1, 3, 3);

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save('php://output');
    }

    private function style($sheet, int $columns, int $lastRow, int $firstMoneyColumn, int $lastMoneyColumn): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columns);
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        for ($c = 1; $c <= $columns; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        if ($lastRow >= 2) {
            $from = Coordinate::stringFromColumnIndex($firstMoneyColumn);
            $to = Coordinate::stringFromColumnIndex($lastMoneyColumn);
            $sheet->getStyle("{$from}2:{$to}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }
}
