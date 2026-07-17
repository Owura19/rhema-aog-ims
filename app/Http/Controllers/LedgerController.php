<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LedgerController extends Controller
{
    /**
     * GENERAL LEDGER — every account with total debits, credits, and balance.
     */
    public function index(Request $request)
    {
        $asOf = $request->get('as_of', now()->toDateString());

        $sums = JournalLine::select(
                'account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->whereHas('entry', fn($q) => $q->whereDate('entry_date', '<=', $asOf))
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = Account::where('is_group', false)
            ->orderBy('type')->orderBy('code')
            ->get()
            ->map(function ($acct) use ($sums) {
                $row = $sums->get($acct->id);
                $debit  = $row ? (float) $row->total_debit  : 0;
                $credit = $row ? (float) $row->total_credit : 0;
                // Balance on the account's natural side
                $balance = in_array($acct->type, ['Asset', 'Expense'])
                    ? $debit - $credit
                    : $credit - $debit;
                return (object) [
                    'account' => $acct,
                    'debit'   => $debit,
                    'credit'  => $credit,
                    'balance' => $balance,
                    'active'  => ($debit != 0 || $credit != 0),
                ];
            });

        return view('ledger.index', compact('accounts', 'asOf'));
    }

    /**
     * ACCOUNT STATEMENT — one account's entries in date order with a running balance.
     */
    public function show(Request $request, Account $account)
    {
        $from = $request->get('from');
        $to   = $request->get('to', now()->toDateString());

        // Opening balance = net of all lines BEFORE the 'from' date
        $opening = 0.0;
        if ($from) {
            $prior = JournalLine::where('account_id', $account->id)
                ->whereHas('entry', fn($q) => $q->whereDate('entry_date', '<', $from))
                ->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
            $d = (float) ($prior->d ?? 0);
            $c = (float) ($prior->c ?? 0);
            $opening = in_array($account->type, ['Asset', 'Expense']) ? $d - $c : $c - $d;
        }

        $lines = JournalLine::with('entry')
            ->where('account_id', $account->id)
            ->whereHas('entry', function ($q) use ($from, $to) {
                if ($from) $q->whereDate('entry_date', '>=', $from);
                $q->whereDate('entry_date', '<=', $to);
            })
            ->get()
            ->sortBy(fn($l) => $l->entry->entry_date)
            ->values();

        // Build running balance
        $isDebitNormal = in_array($account->type, ['Asset', 'Expense']);
        $running = $opening;
        $rows = $lines->map(function ($l) use (&$running, $isDebitNormal) {
            $delta = $isDebitNormal ? ($l->debit - $l->credit) : ($l->credit - $l->debit);
            $running += $delta;
            return (object) [
                'date'    => $l->entry->entry_date,
                'ref'     => $l->entry->reference,
                'desc'    => $l->entry->description,
                'debit'   => (float) $l->debit,
                'credit'  => (float) $l->credit,
                'balance' => $running,
            ];
        });

        $totalDebit  = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $closing     = $running;

        return view('ledger.show', compact('account', 'rows', 'opening', 'closing', 'totalDebit', 'totalCredit', 'from', 'to'));
    }
}