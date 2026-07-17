<?php

namespace App\Http\Controllers;

use App\Models\Harvest;
use Illuminate\Http\Request;

class HarvestController extends Controller
{
    // ── LIST CAMPAIGNS ──────────────────────────────────────────
    public function index()
    {
        $harvests = Harvest::withCount('pledges')->orderByDesc('year')->get();

        return view('harvests.index', compact('harvests'));
    }

    // ── CREATE FORM ─────────────────────────────────────────────
    public function create()
    {
        return view('harvests.create');
    }

    // ── STORE ───────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'year'          => 'required|integer|min:2000|max:2100',
            'target_amount' => 'required|numeric|min:0',
            'harvest_date'  => 'nullable|date',
            'pledge_opens'  => 'nullable|date',
            'description'   => 'nullable|string',
        ]);

        $validated['status'] = 'Active';

        $harvest = Harvest::create($validated);

        return redirect()
            ->route('harvests.show', $harvest)
            ->with('success', $harvest->name . ' created.');
    }

    public function edit(Harvest $harvest)
    {
        return view('harvests.edit', compact('harvest'));
    }

    public function update(Request $request, Harvest $harvest)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'year'          => 'required|integer|min:2000|max:2100',
            'target_amount' => 'required|numeric|min:0',
            'harvest_date'  => 'nullable|date',
            'pledge_opens'  => 'nullable|date',
            'status'        => 'required|in:Active,Completed,Cancelled',
            'description'   => 'nullable|string',
        ]);

        $harvest->update($validated);

        return redirect()
            ->route('harvests.show', $harvest)
            ->with('success', $harvest->name . ' updated.');
    }

    // ── DASHBOARD ───────────────────────────────────────────────
    public function show(Harvest $harvest)
    {
        // All harvest pledges with their pledger + payment totals
        $pledges = $harvest->pledges()
            ->with(['member', 'payments'])
            ->where('status', '!=', 'Cancelled')
            ->orderByDesc('amount_pledged')
            ->get();

        return view('harvests.show', compact('harvest', 'pledges'));
    }

    // ── DELETE ──────────────────────────────────────────────────
    public function destroy(Harvest $harvest)
    {
        $harvest->delete();

        return redirect()
            ->route('harvests.index')
            ->with('success', 'Harvest campaign deleted.');
    }
}