<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $query = Member::with('family')->latest();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('member_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('membership_status', $request->status);
        }

        // Filter by gender
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        // Filter by member type
        if ($request->filled('member_type')) {
            $query->where('member_type', $request->member_type);
        }

        $members = $query->paginate(20)->withQueryString();

        $stats = [
            'total'    => Member::count(),
            'active'   => Member::where('membership_status', 'Active')->count(),
            'visitors' => Member::where('membership_status', 'Visitor')->count(),
            'inactive' => Member::where('membership_status', 'Inactive')->count(),
        ];

        return view('members.index', compact('members', 'stats'));
    }

    public function create()
    {
        $families = Family::orderBy('family_name')->get();
        return view('members.create', compact('families'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'              => 'required|string|max:255',
            'last_name'               => 'required|string|max:255',
            'other_name'              => 'nullable|string|max:255',
            'email'                   => 'nullable|email|unique:members,email',
            'phone'                   => 'nullable|string|max:20',
            'alt_phone'               => 'nullable|string|max:20',
            'gender'                  => 'required|in:Male,Female',
            'date_of_birth'           => 'nullable|date',
            'occupation'              => 'nullable|string|max:255',
            'employer'                => 'nullable|string|max:255',
            'marital_status'          => 'nullable|in:Single,Married,Divorced,Widowed',
            'residential_address'     => 'nullable|string|max:255',
            'digital_address'         => 'nullable|string|max:255',
            'emergency_contact_name'  => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'date_joined'             => 'nullable|date',
            'date_baptized'           => 'nullable|date',
            'membership_status'       => 'required|in:Active,Inactive,Visitor,Transferred,Deceased',
            'member_type'             => 'required|in:Full Member,Associate,Visitor',
            'family_id'               => 'nullable|exists:families,id',
            'family_role'             => 'nullable|string|max:50',
            'fingerprint_id'          => 'nullable|integer',
            'notes'                   => 'nullable|string',
            'photo'                   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('members/photos', 'public');
        }

        $validated['created_by'] = auth()->id();

        $member = Member::create($validated);

        return redirect()->route('members.show', $member)
            ->with('success', "Member {$member->full_name} added successfully! ID: {$member->member_id}");
    }

  public function show(Member $member)
    {
        $member->load([
            'family',
            'createdBy',
            'relationships.relatedMember',
        ]);

        // Other members for the "add relationship" dropdown
        $otherMembers = Member::where('id', '!=', $member->id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('members.show', compact('member', 'otherMembers'));
    }

    // ── EDIT FORM ────────────────────────────────────────────────
    public function edit(Member $member)
    {
        $families = \App\Models\Family::orderBy('family_name')->get();
        return view('members.edit', compact('member', 'families'));
    }

    // ── UPDATE ───────────────────────────────────────────────────
    public function update(Request $request, Member $member)
    {
        // Mirror the same validation your store() uses.
        // Adjust field names/rules here if your store() differs.
        $validated = $request->validate([
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'gender'          => 'nullable|string',
            'date_of_birth'   => 'nullable|date',
            'marital_status'  => 'nullable|string',
            'phone'           => 'nullable|string|max:30',
            'alt_phone'       => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'residential_address' => 'nullable|string',
            'digital_address' => 'nullable|string|max:100',
            'occupation'      => 'nullable|string|max:255',
            'employer'        => 'nullable|string|max:255',
            'member_type'     => 'nullable|string',
            'status'          => 'nullable|string',
            'family_id'       => 'nullable|exists:families,id',
            'family_role'     => 'nullable|string|max:50',
            'photo'           => 'nullable|image|max:4096',
        ]);

        // Handle photo upload if a new one is provided
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('members', 'public');
        }

        $member->update($validated);

        return redirect()
            ->route('members.show', $member)
            ->with('success', "Member {$member->full_name} updated successfully.");
    }

    // ── DELETE ───────────────────────────────────────────────────
    public function destroy(Member $member)
    {
        // Remove this member's family relationship links first
        \App\Models\MemberRelationship::where('member_id', $member->id)
            ->orWhere('related_member_id', $member->id)
            ->delete();

        $name = $member->full_name;
        $member->delete();

        return redirect()
            ->route('members.index')
            ->with('success', "Member {$name} deleted.");
    }
}