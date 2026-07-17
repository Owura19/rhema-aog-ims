<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Pledge;
use Illuminate\Support\Carbon;

class MemberPortalController extends Controller
{
    /**
     * The member's own dashboard.
     * SECURITY: every query is filtered by the member_id tied to the
     * LOGGED-IN account (auth()->user()->member_id), never from the URL.
     * A member can only ever see their own data.
     */
    public function dashboard()
    {
        $user   = auth()->user();
        $member = $user->member;

        // Safety: if this account isn't linked to a member, block access.
        if (!$member) {
            abort(403, 'This account is not linked to a member record.');
        }

        $memberId = $member->id;
        $year     = now()->year;

        // ---- Own giving (transactions tagged to this member) ----
        $givingThisYear = Transaction::where('member_id', $memberId)
            ->where('type', '!=', 'Expense')
            ->whereYear('transaction_date', $year)
            ->sum('amount');

        $givingAllTime = Transaction::where('member_id', $memberId)
            ->where('type', '!=', 'Expense')
            ->sum('amount');

        $recentGiving = Transaction::where('member_id', $memberId)
            ->where('type', '!=', 'Expense')
            ->latest('transaction_date')
            ->limit(10)
            ->get();

            // ---- Giving broken down by type (this member only) ----
        $givingByType = Transaction::where('member_id', $memberId)
            ->where('type', '!=', 'Expense')
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        // ---- Own pledges ----
        $pledges = Pledge::where('member_id', $memberId)
            ->with('purpose')
            ->latest()
            ->get();

        $activePledges = $pledges->where('status', 'Active');

        return view('portal.dashboard', compact(
            'member',
            'givingThisYear',
            'givingAllTime',
            'givingByType',
            'recentGiving',
            'pledges',
            'activePledges',
            'year'
        ));
    }

    /**
     * The member's own giving history (full list).
     */
    public function giving()
    {
        $member = auth()->user()->member;
        if (!$member) {
            abort(403);
        }

        $giving = Transaction::where('member_id', $member->id)
            ->where('type', '!=', 'Expense')
            ->latest('transaction_date')
            ->paginate(25);

        return view('portal.giving', compact('member', 'giving'));
    }

    /**
     * The member's own profile.
     */
    public function profile()
    {
        $member = auth()->user()->member;
        if (!$member) {
            abort(403);
        }

        return view('portal.profile', compact('member'));
    }
}