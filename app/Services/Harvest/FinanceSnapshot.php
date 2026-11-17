<?php

namespace App\Services\Harvest;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Transaction;

/**
 * What linking harvest money must never change: Finance totals and the journal. Taken
 * before and after a batch of links; any difference rolls the batch back.
 */
final class FinanceSnapshot
{
    public static function take(): array
    {
        return [
            'income'        => number_format((float) Transaction::where('category', 'Income')->sum('amount'), 2, '.', ''),
            'expense'       => number_format((float) Transaction::where('category', 'Expense')->sum('amount'), 2, '.', ''),
            'transactions'  => Transaction::withTrashed()->count(),
            'journals'      => JournalEntry::count(),
            'journal_lines' => JournalLine::count(),
        ];
    }
}
