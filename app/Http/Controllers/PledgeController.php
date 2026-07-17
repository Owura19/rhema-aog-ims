<?php

namespace App\Http\Controllers;

use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Member;
use Illuminate\Http\Request;

class PledgeController extends Controller
{
    // ── LIST ────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Pledge::with(['member', 'purpose'])->latest();

        // Optional filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('purpose')) {
            $query->where('pledge_purpose_id', $request->purpose);
        }

        $pledges = $query->paginate(20)->withQueryString();

        // Summary figures for the top of the page
        $summary = [
            'total_pledged'   => Pledge::where('status', '!=', 'Cancelled')->sum('amount_pledged'),
            'total_collected' => \App\Models\PledgePayment::sum('amount'),
            'active_count'    => Pledge::where('status', 'Active')->count(),
            'fulfilled_count' => Pledge::where('status', 'Fulfilled')->count(),
        ];
        $summary['outstanding'] = max(0, $summary['total_pledged'] - $summary['total_collected']);

        $purposes = PledgePurpose::orderBy('name')->get();

        return view('pledges.index', compact('pledges', 'summary', 'purposes'));
    }

    // ── CREATE FORM ─────────────────────────────────────────────
    public function create()
    {
        $members  = Member::orderBy('first_name')->get();
        $purposes = PledgePurpose::active()->orderBy('name')->get();

        $harvests = \App\Models\Harvest::where('status', 'Active')->orderBy('name')->get();
        return view('pledges.create', compact('members', 'purposes', 'harvests'));
    }

    // ── STORE ───────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id'         => 'nullable|exists:members,id',
            'pledger_name'      => 'nullable|string|max:255',
            'pledge_purpose_id' => 'required|exists:pledge_purposes,id',
            'amount_pledged'    => 'required|numeric|min:0.01',
            'date_pledged'      => 'required|date',
            'target_date'       => 'nullable|date|after_or_equal:date_pledged',
            'notes'             => 'nullable|string',
        ]);

        // Must have either a member or a name
        if (empty($validated['member_id']) && empty($validated['pledger_name'])) {
            return back()->withInput()->withErrors([
                'pledger_name' => 'Select a member or enter a pledger name.',
            ]);
        }

        $validated['recorded_by'] = auth()->id();
        $validated['status']      = 'Active';

        $pledge = Pledge::create($validated);

        return redirect()
            ->route('pledges.show', $pledge)
            ->with('success', 'Pledge ' . $pledge->reference . ' recorded successfully.');
    }

    // ── SHOW (details + payments) ───────────────────────────────
    public function show(Pledge $pledge)
    {
        $pledge->load(['member', 'purpose', 'payments' => fn($q) => $q->latest('payment_date'), 'recordedBy']);

        return view('pledges.show', compact('pledge'));
    }

    // ── RECORD A PAYMENT ────────────────────────────────────────
    public function storePayment(Request $request, Pledge $pledge)
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_date'   => 'required|date',
            'payment_method' => 'required|in:Cash,Mobile Money,Bank Transfer,Cheque,Other',
            'notes'          => 'nullable|string',
        ]);

        if ($pledge->status === 'Cancelled') {
            return back()->with('error', 'Cannot record a payment on a cancelled pledge.');
        }

        $validated['recorded_by'] = auth()->id();

        // Creating the payment auto-posts a finance transaction (see model)
        $pledge->payments()->create($validated);

        return redirect()
            ->route('pledges.show', $pledge)
            ->with('success', 'Payment of GHS ' . number_format($validated['amount'], 2) . ' recorded.');
    }

    // ── CANCEL A PLEDGE ─────────────────────────────────────────
    public function cancel(Pledge $pledge)
    {
        $pledge->update(['status' => 'Cancelled']);

        return back()->with('success', 'Pledge ' . $pledge->reference . ' cancelled.');
    }

    // ── DELETE ──────────────────────────────────────────────────
    public function destroy(Pledge $pledge)
    {
        $pledge->delete();

        return redirect()
            ->route('pledges.index')
            ->with('success', 'Pledge deleted.');
    }
}