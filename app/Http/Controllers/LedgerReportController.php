<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LedgerReportController extends Controller
{
    /**
     * TRIAL BALANCE
     * Every account with its total debits and credits from the journal.
     * Total debits must equal total credits (proof the books balance).
     */
    public function trialBalance(Request $request)
    {
        $asOf = $request->get('as_of', now()->toDateString());

        // Sum debit/credit per account from journal lines up to the date
        $sums = JournalLine::select(
                'account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->whereHas('entry', fn($q) => $q->whereDate('entry_date', '<=', $asOf))
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        // Build rows for every account that has activity
        $accounts = Account::where('is_group', false)
            ->orderBy('code')
            ->get()
            ->map(function ($acct) use ($sums) {
                $row = $sums->get($acct->id);
                $debit  = $row ? (float) $row->total_debit  : 0;
                $credit = $row ? (float) $row->total_credit : 0;
                $net    = $debit - $credit;

                // Present the net on its natural side
                return (object) [
                    'account'    => $acct,
                    'debit'      => $net > 0 ? $net : 0,
                    'credit'     => $net < 0 ? abs($net) : 0,
                    'has_activity' => ($debit != 0 || $credit != 0),
                ];
            })
            ->filter(fn($r) => $r->has_activity)
            ->values();

        $totalDebit  = $accounts->sum('debit');
        $totalCredit = $accounts->sum('credit');
        $balanced    = round($totalDebit, 2) === round($totalCredit, 2);

        return view('finance.trial-balance', compact('accounts', 'totalDebit', 'totalCredit', 'balanced', 'asOf'));
    }

    /**
     * BALANCE SHEET
     * Assets = Liabilities + Equity (+ current surplus/deficit).
     */
    public function balanceSheet(Request $request)
    {
        $asOf = $request->get('as_of', now()->toDateString());

        // Net movement per account from the journal
        $sums = JournalLine::select(
                'account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->whereHas('entry', fn($q) => $q->whereDate('entry_date', '<=', $asOf))
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        // Helper: balance of an account in its natural sign
        $balanceOf = function (Account $a) use ($sums) {
            $row = $sums->get($a->id);
            $debit  = $row ? (float) $row->total_debit  : 0;
            $credit = $row ? (float) $row->total_credit : 0;
            // Assets & Expenses: debit-normal (debit - credit)
            // Liabilities, Equity, Income: credit-normal (credit - debit)
            return in_array($a->type, ['Asset', 'Expense'])
                ? $debit - $credit
                : $credit - $debit;
        };

        $postable = Account::where('is_group', false)->orderBy('code')->get();

        $assets = $postable->where('type', 'Asset')
            ->map(fn($a) => (object)['account' => $a, 'balance' => $balanceOf($a)])
            ->filter(fn($r) => $r->balance != 0)->values();

        $liabilities = $postable->where('type', 'Liability')
            ->map(fn($a) => (object)['account' => $a, 'balance' => $balanceOf($a)])
            ->filter(fn($r) => $r->balance != 0)->values();

        $equity = $postable->where('type', 'Equity')
            ->map(fn($a) => (object)['account' => $a, 'balance' => $balanceOf($a)])
            ->filter(fn($r) => $r->balance != 0)->values();

        // Current period surplus/deficit = total income - total expense (from journal)
        $totalIncome = $postable->where('type', 'Income')->sum(fn($a) => $balanceOf($a));
        $totalExpense = $postable->where('type', 'Expense')->sum(fn($a) => $balanceOf($a));
        $surplus = $totalIncome - $totalExpense;

        $totalAssets      = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquity      = $equity->sum('balance') + $surplus;

        $balanced = round($totalAssets, 2) === round($totalLiabilities + $totalEquity, 2);

        return view('finance.balance-sheet', compact(
            'assets', 'liabilities', 'equity',
            'totalAssets', 'totalLiabilities', 'totalEquity',
            'surplus', 'balanced', 'asOf'
        ));
    }
}