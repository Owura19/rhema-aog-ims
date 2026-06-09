<?php

namespace App\Http\Controllers;

use App\Models\ChurchService;
use App\Models\AttendanceLog;
use App\Models\Member;
use Illuminate\Http\Request;

class ChurchServiceController extends Controller
{
    public function index()
    {
        $services = ChurchService::withCount('attendanceLogs')
            ->latest('service_date')
            ->paginate(20);

        $stats = [
            'total'     => ChurchService::count(),
            'scheduled' => ChurchService::where('status', 'Scheduled')->count(),
            'completed' => ChurchService::where('status', 'Completed')->count(),
            'this_month' => ChurchService::whereMonth('service_date', now()->month)->count(),
        ];

        return view('attendance.services.index', compact('services', 'stats'));
    }

    public function create()
    {
        return view('attendance.services.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'service_type'       => 'required|in:Sunday Service,Midweek Service,Prayer Meeting,Special Event,Convention,Other',
            'service_date'       => 'required|date',
            'start_time'         => 'required',
            'end_time'           => 'nullable',
            'venue'              => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'status'             => 'required|in:Scheduled,Ongoing,Completed,Cancelled',
            'biometric_enabled'  => 'boolean',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['biometric_enabled'] = $request->has('biometric_enabled');

        $service = ChurchService::create($validated);

        return redirect()->route('services.show', $service)
            ->with('success', "Service '{$service->name}' created successfully!");
    }

    public function show(ChurchService $service)
    {
        $service->load('createdBy');

        $attendance = AttendanceLog::where('church_service_id', $service->id)
            ->with('member')
            ->orderBy('check_in_time')
            ->get();

        $stats = [
            'present'  => $attendance->where('status', 'Present')->count(),
            'late'     => $attendance->where('status', 'Late')->count(),
            'absent'   => $attendance->where('status', 'Absent')->count(),
            'excused'  => $attendance->where('status', 'Excused')->count(),
            'total'    => Member::where('membership_status', 'Active')->count(),
        ];

        $unmarkedMembers = Member::where('membership_status', 'Active')
            ->whereNotIn('id', $attendance->pluck('member_id'))
            ->get();

        return view('attendance.services.show', compact('service', 'attendance', 'stats', 'unmarkedMembers'));
    }

    public function edit(ChurchService $service)
    {
        return view('attendance.services.edit', compact('service'));
    }

    public function update(Request $request, ChurchService $service)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'service_type'       => 'required|in:Sunday Service,Midweek Service,Prayer Meeting,Special Event,Convention,Other',
            'service_date'       => 'required|date',
            'start_time'         => 'required',
            'end_time'           => 'nullable',
            'venue'              => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'status'             => 'required|in:Scheduled,Ongoing,Completed,Cancelled',
            'biometric_enabled'  => 'boolean',
        ]);

        $validated['biometric_enabled'] = $request->has('biometric_enabled');

        $service->update($validated);

        return redirect()->route('services.show', $service)
            ->with('success', "Service updated successfully!");
    }

    public function destroy(ChurchService $service)
    {
        $service->delete();
        return redirect()->route('services.index')
            ->with('success', 'Service deleted successfully.');
    }
}