<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\ChurchService;
use App\Models\Member;
use App\Models\BiometricDevice;
use App\Services\BiometricService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected BiometricService $biometricService;

    public function __construct(BiometricService $biometricService)
    {
        $this->biometricService = $biometricService;
    }

    public function index(Request $request)
    {
        $query = AttendanceLog::with(['member', 'churchService'])->latest();

        if ($request->filled('service_id')) {
            $query->where('church_service_id', $request->service_id);
        }

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->paginate(20)->withQueryString();
        $services = ChurchService::latest('service_date')->get();
        $members = Member::orderBy('first_name')->get();

        return view('attendance.index', compact('logs', 'services', 'members'));
    }

    public function mark(Request $request)
    {
        $validated = $request->validate([
            'church_service_id' => 'required|exists:church_services,id',
            'member_id'         => 'required|exists:members,id',
            'status'            => 'required|in:Present,Late,Absent,Excused',
            'notes'             => 'nullable|string',
        ]);

        $validated['check_in_method'] = 'Manual';
        $validated['check_in_time']   = now();
        $validated['marked_by']       = auth()->id();

        AttendanceLog::updateOrCreate(
            [
                'church_service_id' => $validated['church_service_id'],
                'member_id'         => $validated['member_id'],
            ],
            $validated
        );

        return back()->with('success', 'Attendance marked successfully!');
    }

    public function bulkMark(Request $request)
    {
        $validated = $request->validate([
            'church_service_id' => 'required|exists:church_services,id',
            'member_ids'        => 'required|array',
            'member_ids.*'      => 'exists:members,id',
            'status'            => 'required|in:Present,Late,Absent,Excused',
        ]);

        foreach ($validated['member_ids'] as $memberId) {
            AttendanceLog::updateOrCreate(
                [
                    'church_service_id' => $validated['church_service_id'],
                    'member_id'         => $memberId,
                ],
                [
                    'status'           => $validated['status'],
                    'check_in_method'  => 'Manual',
                    'check_in_time'    => now(),
                    'marked_by'        => auth()->id(),
                ]
            );
        }

        return back()->with('success', count($validated['member_ids']) . ' members marked as ' . $validated['status']);
    }

    public function syncBiometric(Request $request)
    {
        $validated = $request->validate([
            'church_service_id'   => 'required|exists:church_services,id',
            'biometric_device_id' => 'required|exists:biometric_devices,id',
        ]);

        $service = ChurchService::findOrFail($validated['church_service_id']);
        $device  = BiometricDevice::findOrFail($validated['biometric_device_id']);

        $results = $this->biometricService->syncDevice($device, $service);

        if ($results['success']) {
            return back()->with('success', $results['message']);
        }

        return back()->with('error', $results['message']);
    }

    public function report(Request $request)
    {
        $services = ChurchService::where('status', 'Completed')
            ->latest('service_date')
            ->take(12)
            ->get();

        $reportData = $services->map(function ($service) {
            return [
                'service'    => $service,
                'present'    => AttendanceLog::where('church_service_id', $service->id)->whereIn('status', ['Present', 'Late'])->count(),
                'total'      => Member::where('membership_status', 'Active')->count(),
                'percentage' => $service->attendance_percentage,
            ];
        });

        return view('attendance.report', compact('reportData'));
    }

    public function destroy(AttendanceLog $attendanceLog)
    {
        $attendanceLog->delete();
        return back()->with('success', 'Attendance record removed.');
    }
}