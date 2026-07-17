<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;

class MemberLedgerController extends Controller
{
    /**
     * OVERALL GIVING REPORT — all members ranked by total given, over a period.
     */
    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfYear()->toDateString());
        $to   = $request->get('to', now()->toDateString());

        $givers = Transaction::selectRaw('member_id, SUM(amount) as total, COUNT(*) as gifts')
            ->where('category', 'Income')
            ->whereNotNull('member_id')
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->groupBy('member_id')
            ->orderByDesc('total')
            ->with('member')
            ->get()
            ->filter(fn($r) => $r->member); // drop any orphaned member_ids

        $grandTotal = $givers->sum('total');

        return view('member-ledger.index', compact('givers', 'grandTotal', 'from', 'to'));
    }

    /**
     * PER-MEMBER STATEMENT — one member's giving: summary by type + itemized list.
     */
    public function show(Request $request, Member $member)
    {
        [$from, $to, $summary, $items, $total] = $this->buildStatement($request, $member);
        return view('member-ledger.show', compact('member', 'summary', 'items', 'total', 'from', 'to'));
    }

    /**
     * Printable version of a member's giving statement.
     */
    public function print(Request $request, Member $member)
    {
        [$from, $to, $summary, $items, $total] = $this->buildStatement($request, $member);
        return view('member-ledger.print', compact('member', 'summary', 'items', 'total', 'from', 'to'));
    }

    private function buildStatement(Request $request, Member $member): array
    {
        $from = $request->get('from', now()->startOfYear()->toDateString());
        $to   = $request->get('to', now()->toDateString());

        $base = Transaction::where('member_id', $member->id)
            ->where('category', 'Income')
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to);

        // Summary by type
        $summary = (clone $base)
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as gifts')
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        // Itemized list
        $items = (clone $base)
            ->orderBy('transaction_date')
            ->get(['transaction_date', 'type', 'reference', 'amount', 'payment_method']);

        $total = (clone $base)->sum('amount');

        return [$from, $to, $summary, $items, $total];
    }
}