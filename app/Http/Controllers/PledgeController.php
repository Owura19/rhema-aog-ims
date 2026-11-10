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
        if ($request->filled('year')) {
            $query->whereYear('date_pledged', $request->year);
        }
        if ($request->filled('harvest')) {
            $query->where('harvest_id', $request->harvest);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('reference', 'like', "%{$term}%")
                  ->orWhere('pledger_name', 'like', "%{$term}%")
                  ->orWhereHas('member', function ($m) use ($term) {
                      $m->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]);
                  });
            });
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
        $years = Pledge::selectRaw('DISTINCT YEAR(date_pledged) as y')->orderByDesc('y')->pluck('y');
        $harvests = \App\Models\Harvest::orderBy('name')->get();

        return view('pledges.index', compact('pledges', 'summary', 'purposes', 'years', 'harvests'));
    }

    public function report(Request $request)
    {
        $q = Pledge::with(['member', 'purpose'])
            ->where('status', '!=', 'Cancelled');

        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('purpose')) $q->where('pledge_purpose_id', $request->purpose);
        if ($request->filled('date_from')) $q->whereDate('date_pledged', '>=', $request->date_from);
        if ($request->filled('date_to'))   $q->whereDate('date_pledged', '<=', $request->date_to);
        if ($request->filled('harvest')) $q->where('harvest_id', $request->harvest);
        if ($request->filled('service')) $q->where('church_service_id', $request->service);
        if ($request->filled('year'))    $q->whereYear('date_pledged', $request->year);

        $pledges = $q->get()->map(function ($p) {
            $paid = $p->payments()->sum('amount');
            $bal  = max(0, (float)$p->amount_pledged - (float)$paid);
            $name = $p->member
                ? trim($p->member->first_name . ' ' . $p->member->last_name)
                : ($p->pledger_name ?? 'Unknown');
            return (object) [
                'id'       => $p->id,
                'name'     => $name,
                'member'   => $p->member,
                'purpose'  => optional($p->purpose)->name ?? '—',
                'pledged'  => (float)$p->amount_pledged,
                'paid'     => (float)$paid,
                'balance'  => $bal,
                'percent'  => $p->amount_pledged > 0 ? round($paid / $p->amount_pledged * 100) : 0,
                'status'   => $p->status,
            ];
        })->sortByDesc('balance')->values();

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $pledges = $pledges->where('balance', '<=', 0)->values();
            } elseif ($request->payment_status === 'owing') {
                $pledges = $pledges->where('balance', '>', 0)->values();
            }
        }

        $totals = [
            'pledged'     => $pledges->sum('pledged'),
            'paid'        => $pledges->sum('paid'),
            'outstanding' => $pledges->sum('balance'),
            'count'       => $pledges->count(),
            'fulfilled'   => $pledges->where('balance', '<=', 0)->count(),
        ];

        $purposes = PledgePurpose::orderBy('name')->get();
        $harvests = \App\Models\Harvest::orderBy('name')->get();
        $services = \App\Models\ChurchService::forPicker($request->integer('service') ?: null)->get();   // keeps a filtered-on service listed
        $years = Pledge::selectRaw('DISTINCT YEAR(date_pledged) as y')->orderByDesc('y')->pluck('y');
        return view('pledges.report', compact('pledges', 'totals', 'purposes', 'harvests', 'services', 'years'));
    }

    // ── PLEDGES REPORT PDF (all pledges, no filters) ────────────
    public function reportPdf(Request $request)
    {
        $q = Pledge::with(['member', 'purpose'])
            ->where('status', '!=', 'Cancelled');

        if ($request->filled('status'))    $q->where('status', $request->status);
        if ($request->filled('purpose'))   $q->where('pledge_purpose_id', $request->purpose);
        if ($request->filled('date_from')) $q->whereDate('date_pledged', '>=', $request->date_from);
        if ($request->filled('date_to'))   $q->whereDate('date_pledged', '<=', $request->date_to);
        if ($request->filled('harvest'))   $q->where('harvest_id', $request->harvest);
        if ($request->filled('service'))   $q->where('church_service_id', $request->service);
        if ($request->filled('year'))      $q->whereYear('date_pledged', $request->year);

        $pledges = $q->get()
            ->map(function ($p) {
                $paid = $p->payments()->sum('amount');
                $bal  = max(0, (float)$p->amount_pledged - (float)$paid);
                $name = $p->member
                    ? trim($p->member->first_name . ' ' . $p->member->last_name)
                    : ($p->pledger_name ?? 'Unknown');
                return (object) [
                    'name'    => $name,
                    'purpose' => optional($p->purpose)->name ?? '-',
                    'pledged' => (float)$p->amount_pledged,
                    'paid'    => (float)$paid,
                    'balance' => $bal,
                    'percent' => $p->amount_pledged > 0 ? round($paid / $p->amount_pledged * 100) : 0,
                ];
            })->sortByDesc('balance')->values();

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $pledges = $pledges->where('balance', '<=', 0)->values();
            } elseif ($request->payment_status === 'owing') {
                $pledges = $pledges->where('balance', '>', 0)->values();
            }
        }

        $totals = [
            'pledged'     => $pledges->sum('pledged'),
            'paid'        => $pledges->sum('paid'),
            'outstanding' => $pledges->sum('balance'),
            'count'       => $pledges->count(),
            'fulfilled'   => $pledges->where('balance', '<=', 0)->count(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pledges.pdf.report', [
            'pledges'     => $pledges,
            'totals'      => $totals,
            'churchName'  => config('app.name'),
            'generatedAt' => now()->format('M d, Y g:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Pledges-Report-' . now()->format('Ymd') . '.pdf');
    }

    // ── CREATE FORM ─────────────────────────────────────────────
    public function create()
    {
        $members  = Member::orderBy('first_name')->get();
        $purposes = PledgePurpose::active()->orderBy('name')->get();

        $harvests = \App\Models\Harvest::where('status', 'Active')->orderBy('name')->get();
        $services = \App\Models\ChurchService::forPicker()->get();
        return view('pledges.create', compact('members', 'purposes', 'harvests', 'services'));
    }

    // ── STORE ───────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id'         => 'nullable|exists:members,id',
            'pledger_name'      => 'nullable|string|max:255',
            'pledge_purpose_id' => 'required|exists:pledge_purposes,id',
            'harvest_id'        => 'nullable|exists:harvests,id',
            'church_service_id' => 'nullable|exists:church_services,id',
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

        // Backstop against a stale member_id lingering from a prior submission
        // on the same page: if the form explicitly says this is a non-member
        // pledge, never save a member_id even if one slipped through.
        if ($request->input('pledger_type') === 'other') {
            $validated['member_id'] = null;
        } elseif ($request->input('pledger_type') === 'member') {
            $validated['pledger_name'] = null;
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

    // ── EDIT FORM (edit pledges permission) ─────────────────────
    public function edit(Pledge $pledge)
    {
        $members  = Member::orderBy('first_name')->get();
        $purposes = PledgePurpose::orderBy('name')->get();
        $harvests = \App\Models\Harvest::orderBy('name')->get();
        $services = \App\Models\ChurchService::forPicker($pledge->church_service_id)->get();   // keeps this record's own service listed
        return view('pledges.edit', compact('pledge', 'members', 'purposes', 'harvests', 'services'));
    }

    // ── UPDATE ──────────────────────────────────────────────────
    public function update(Request $request, Pledge $pledge)
    {
        $validated = $request->validate([
            'member_id'         => 'nullable|exists:members,id',
            'pledger_name'      => 'nullable|string|max:255',
            'pledge_purpose_id' => 'required|exists:pledge_purposes,id',
            'harvest_id'        => 'nullable|exists:harvests,id',
            'church_service_id' => 'nullable|exists:church_services,id',
            'amount_pledged'    => 'required|numeric|min:0.01',
            'date_pledged'      => 'required|date',
            'target_date'       => 'nullable|date|after_or_equal:date_pledged',
            'status'            => 'required|in:Active,Fulfilled,Cancelled',
            'notes'             => 'nullable|string',
        ]);
        if (empty($validated['member_id']) && empty($validated['pledger_name'])) {
            return back()->withInput()->withErrors([
                'pledger_name' => 'Select a member or enter a pledger name.',
            ]);
        }
        // If a member is chosen, clear the free-text name (and vice versa)
        if (!empty($validated['member_id'])) {
            $validated['pledger_name'] = null;
        }
        $pledge->update($validated);
        return redirect()->route('pledges.show', $pledge)->with('success', 'Pledge updated.');
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

    // ── EDIT A PAYMENT (edit pledges permission) ────────────────
    public function editPayment(Pledge $pledge, \App\Models\PledgePayment $payment)
    {
        abort_unless($payment->pledge_id === $pledge->id, 404);
        return view('pledges.edit-payment', compact('pledge', 'payment'));
    }

    public function updatePayment(Request $request, Pledge $pledge, \App\Models\PledgePayment $payment)
    {
        abort_unless($payment->pledge_id === $pledge->id, 404);
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_date'   => 'required|date',
            'payment_method' => 'required|in:Cash,Mobile Money,Bank Transfer,Cheque,Other',
            'notes'          => 'nullable|string',
        ]);
        $payment->update($validated);
        // Keep a linked finance transaction in sync (no 'updated' hook on the model)
        if ($payment->transaction) {
            $payment->transaction->update([
                'amount'           => $validated['amount'],
                'transaction_date' => $validated['payment_date'],
                'payment_method'   => $validated['payment_method'],
            ]);
        }
        optional($payment->pledge)->refreshStatus();
        return redirect()->route('pledges.show', $pledge)->with('success', 'Payment updated.');
    }

    public function destroyPayment(Pledge $pledge, \App\Models\PledgePayment $payment)
    {
        abort_unless($payment->pledge_id === $pledge->id, 404);
        // Model's deleted() hook voids any linked transaction and refreshes status
        $payment->delete();
        return redirect()->route('pledges.show', $pledge)->with('success', 'Payment deleted.');
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
    // ── PAYMENTS LIST (year-filterable, across all pledges) ──────
    public function paymentsIndex(Request $request)
    {
        $query = \App\Models\PledgePayment::with(['pledge.member', 'pledge.purpose'])->latest('payment_date');

        if ($request->filled('year')) {
            $query->whereYear('payment_date', $request->year);
        }
        if ($request->filled('purpose')) {
            $query->whereHas('pledge', fn($q) => $q->where('pledge_purpose_id', $request->purpose));
        }
        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->whereHas('pledge', function ($q) use ($term) {
                $q->where('reference', 'like', "%{$term}%")
                  ->orWhere('pledger_name', 'like', "%{$term}%")
                  ->orWhereHas('member', function ($m) use ($term) {
                      $m->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]);
                  });
            });
        }

        $payments = $query->paginate(30)->withQueryString();

        $summaryQuery = \App\Models\PledgePayment::query();
        if ($request->filled('year')) {
            $summaryQuery->whereYear('payment_date', $request->year);
        }
        $summaryTotal = $summaryQuery->sum('amount');
        $summaryCount = $summaryQuery->count();

        $purposes = PledgePurpose::orderBy('name')->get();
        $years = \App\Models\PledgePayment::selectRaw('DISTINCT YEAR(payment_date) as y')->orderByDesc('y')->pluck('y');

        return view('pledges.payments-index', compact('payments', 'purposes', 'years', 'summaryTotal', 'summaryCount'));
    }
    // ── PLEDGE SUMMARY BY SERVICE ─────────────────────────────────
    public function summaryByService(Request $request)
    {
        $harvestId = $request->input('harvest');

        $pledgeQuery = Pledge::where('status', '!=', 'Cancelled');
        if ($harvestId) {
            $pledgeQuery->where('harvest_id', $harvestId);
        }
        $pledges = $pledgeQuery->with('payments')->get();

        $rows = $pledges->groupBy('church_service_id')->map(function ($group, $serviceId) {
            $pledgedTotal   = $group->sum('amount_pledged');
            $collectedTotal = $group->sum(fn($p) => $p->payments->sum('amount'));
            return (object) [
                'service'   => $serviceId ? \App\Models\ChurchService::find($serviceId) : null,
                'count'     => $group->count(),
                'pledged'   => $pledgedTotal,
                'collected' => $collectedTotal,
                'outstanding' => max(0, $pledgedTotal - $collectedTotal),
            ];
        })->sortBy(fn($row) => optional($row->service)->service_date ?? '9999-12-31')->values();

        $grand = (object) [
            'count'       => $rows->sum('count'),
            'pledged'     => $rows->sum('pledged'),
            'collected'   => $rows->sum('collected'),
            'outstanding' => $rows->sum('outstanding'),
        ];

        $harvests = \App\Models\Harvest::orderBy('name')->get();

        return view('pledges.summary-by-service', compact('rows', 'grand', 'harvests', 'harvestId'));
    }
}