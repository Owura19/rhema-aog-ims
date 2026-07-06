<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Models\Member;
use App\Models\ChurchService;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function index(Request $request)
    {
        $query = Visitor::with(['churchService', 'followedUpBy', 'recordedBy'])->latest('visit_date');

        if ($request->filled('follow_up_status')) {
            $query->where('follow_up_status', $request->follow_up_status);
        }

        if ($request->filled('visit_type')) {
            $query->where('visit_type', $request->visit_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('visit_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('visit_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $visitors = $query->paginate(20)->withQueryString();

        $stats = [
            'total'          => Visitor::count(),
            'this_month'     => Visitor::whereMonth('visit_date', now()->month)->whereYear('visit_date', now()->year)->count(),
            'pending'        => Visitor::where('follow_up_status', 'Pending')->count(),
            'joined'         => Visitor::where('follow_up_status', 'Joined')->orWhere('converted_to_member', true)->count(),
            'first_time'     => Visitor::where('visit_type', 'First Time')->whereMonth('visit_date', now()->month)->count(),
        ];

        return view('visitors.index', compact('visitors', 'stats'));
    }

    public function create()
    {
        $services = ChurchService::latest('service_date')->take(20)->get();
        return view('visitors.create', compact('services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'required|string|max:255',
            'phone'            => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            'gender'           => 'nullable|in:Male,Female',
            'date_of_birth'    => 'nullable|date',
            'address'          => 'nullable|string|max:255',
            'occupation'       => 'nullable|string|max:255',
            'marital_status'   => 'nullable|in:Single,Married,Divorced,Widowed',
            'how_heard'        => 'nullable|string',
            'church_service_id'=> 'nullable|exists:church_services,id',
            'visit_date'       => 'required|date',
            'visit_type'       => 'required|in:First Time,Second Time,Third Time,Regular',
            'notes'            => 'nullable|string',
        ]);

        $validated['recorded_by']      = auth()->id();
        $validated['follow_up_status'] = 'Pending';

        $visitor = Visitor::create($validated);

        return redirect()->route('visitors.show', $visitor)
            ->with('success', "{$visitor->full_name} recorded as a visitor successfully!");
    }

    public function show(Visitor $visitor)
    {
        $visitor->load(['churchService', 'followedUpBy', 'member', 'recordedBy']);
        return view('visitors.show', compact('visitor'));
    }

    public function edit(Visitor $visitor)
    {
        $services = ChurchService::latest('service_date')->take(20)->get();
        return view('visitors.edit', compact('visitor', 'services'));
    }

    public function update(Request $request, Visitor $visitor)
    {
        $validated = $request->validate([
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'required|string|max:255',
            'phone'            => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            'gender'           => 'nullable|in:Male,Female',
            'date_of_birth'    => 'nullable|date',
            'address'          => 'nullable|string|max:255',
            'occupation'       => 'nullable|string|max:255',
            'marital_status'   => 'nullable|in:Single,Married,Divorced,Widowed',
            'how_heard'        => 'nullable|string',
            'church_service_id'=> 'nullable|exists:church_services,id',
            'visit_date'       => 'required|date',
            'visit_type'       => 'required|in:First Time,Second Time,Third Time,Regular',
            'follow_up_status' => 'required|in:Pending,Called,Visited,Attended Again,Joined,No Response,Not Interested',
            'follow_up_date'   => 'nullable|date',
            'follow_up_notes'  => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $validated['followed_up_by'] = auth()->id();

        $visitor->update($validated);

        return redirect()->route('visitors.show', $visitor)
            ->with('success', "{$visitor->full_name} updated successfully!");
    }

    public function destroy(Visitor $visitor)
    {
        $name = $visitor->full_name;
        $visitor->delete();
        return redirect()->route('visitors.index')
            ->with('success', "{$name} removed from visitors.");
    }

    public function convertToMember(Visitor $visitor)
    {
        if ($visitor->converted_to_member) {
            return back()->with('error', 'This visitor has already been converted to a member.');
        }

        // Create member from visitor data
        $member = Member::create([
            'first_name'        => $visitor->first_name,
            'last_name'         => $visitor->last_name,
            'phone'             => $visitor->phone,
            'email'             => $visitor->email,
            'gender'            => $visitor->gender,
            'date_of_birth'     => $visitor->date_of_birth,
            'address'           => $visitor->address,
            'occupation'        => $visitor->occupation,
            'marital_status'    => $visitor->marital_status,
            'membership_status' => 'Active',
            'join_date'         => now(),
        ]);

        // Update visitor record
        $visitor->update([
            'converted_to_member' => true,
            'member_id'           => $member->id,
            'follow_up_status'    => 'Joined',
        ]);

        return redirect()->route('members.show', $member)
            ->with('success', "{$visitor->full_name} has been converted to a member (ID: {$member->member_id})!");
    }
}