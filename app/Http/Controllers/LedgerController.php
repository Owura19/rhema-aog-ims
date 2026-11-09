<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalLine;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LedgerController extends Controller
{
    /**
     * GENERAL LEDGER — every account with total debits, credits, and balance.
     */
    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfYear()->toDateString());
        $to   = $request->get('to', now()->toDateString());
        $opening = JournalLine::select('account_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
            ->whereHas('entry', fn($q) => $q->whereDate('entry_date', '<', $from))
            ->groupBy('account_id')->get()->keyBy('account_id');
        $asOf = $request->get('as_of', now()->toDateString());

        $sums = JournalLine::select(
                'account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->whereHas('entry', fn($q) => $q->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = Account::where('is_group', false)
            ->orderBy('type')->orderBy('code')
            ->get()
            ->map(function ($acct) use ($sums, $opening) {
                $row = $sums->get($acct->id);
                $op = $opening->get($acct->id);
                $opD = $op ? (float) $op->total_debit : 0;
                $opC = $op ? (float) $op->total_credit : 0;
                $debit  = $row ? (float) $row->total_debit  : 0;
                $credit = $row ? (float) $row->total_credit : 0;
                // Balance on the account's natural side
                $balance = in_array($acct->type, ['Asset', 'Expense'])
                    ? $debit - $credit
                    : $credit - $debit;
                $natural = in_array($acct->type, ['Asset', 'Expense']);
                $openingBal = $natural ? $opD - $opC : $opC - $opD;
                return (object) [
                    'account' => $acct,
                    'opening' => $openingBal,
                    'debit'   => $debit,
                    'credit'  => $credit,
                    'balance' => $balance,
                    'active'  => ($debit != 0 || $credit != 0),
                ];
            });

        return view('ledger.index', compact('accounts', 'from', 'to'));
    }

    /**
     * ACCOUNT STATEMENT — one account's entries in date order with a running balance.
     */
    public function show(Request $request, Account $account)
    {
        [$rows, $opening, $closing, $totalDebit, $totalCredit, $from, $to] = $this->buildStatement($request, $account);

        return view('ledger.show', compact('account', 'rows', 'opening', 'closing', 'totalDebit', 'totalCredit', 'from', 'to'));
    }

    /**
     * ACCOUNT STATEMENT — PDF export with church letterhead.
     */
    public function exportPdf(Request $request, Account $account)
    {
        [$rows, $opening, $closing, $totalDebit, $totalCredit, $from, $to] = $this->buildStatement($request, $account);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('ledger.pdf', compact('account', 'rows', 'opening', 'closing', 'totalDebit', 'totalCredit', 'from', 'to'));
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOptions([
            'dpi'                  => 150,
            'defaultFont'          => 'DejaVu Sans',
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
        ]);

        $filename = 'Ledger_' . $account->code . '_' . now()->format('Y-m-d') . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Shared query logic for both the on-screen statement and the PDF export.
     */
    private function buildStatement(Request $request, Account $account): array
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

        // Pre-fetch the real "purpose" text from source transactions in one query
        $transactionIds = $lines->filter(fn($l) => $l->entry->source_type === 'transaction')
            ->pluck('entry.source_id')->unique()->values();
        $purposes = Transaction::whereIn('id', $transactionIds)->pluck('description', 'id');

        // Build running balance
        $isDebitNormal = in_array($account->type, ['Asset', 'Expense']);
        $running = $opening;
        $rows = $lines->map(function ($l) use (&$running, $isDebitNormal, $purposes) {
            $delta = $isDebitNormal ? ($l->debit - $l->credit) : ($l->credit - $l->debit);
            $running += $delta;

            $purpose = $l->entry->source_type === 'transaction'
                ? ($purposes->get($l->entry->source_id) ?: $l->entry->description)
                : $l->entry->description;

            return (object) [
                'date'    => $l->entry->entry_date,
                'ref'     => $l->entry->reference,
                'desc'    => $l->entry->description,
                'purpose' => $purpose,
                'debit'   => (float) $l->debit,
                'credit'  => (float) $l->credit,
                'balance' => $running,
            ];
        });

        $totalDebit  = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $closing     = $running;

        return [$rows, $opening, $closing, $totalDebit, $totalCredit, $from, $to];
    }
}
