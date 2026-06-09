<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\ChurchService;
use App\Models\AttendanceLog;
use App\Models\Transaction;
use App\Models\CellGroup;
use App\Models\Event;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('Super Admin')) {
            return $this->superAdminDashboard();
        }

        if ($user->hasRole('Pastor')) {
            return $this->pastorDashboard();
        }

        if ($user->hasRole('HOD')) {
            return $this->hodDashboard();
        }

        if ($user->hasRole('Cell Leader')) {
            return $this->cellLeaderDashboard();
        }

        if ($user->hasRole('Data Entry')) {
            return $this->dataEntryDashboard();
        }

        if ($user->hasRole('Usher')) {
            return $this->usherDashboard();
        }

        return $this->superAdminDashboard();
    }

    private function superAdminDashboard()
    {
        $stats = [
            'total_members'   => Member::count(),
            'active_members'  => Member::where('membership_status', 'Active')->count(),
            'services_month'  => ChurchService::whereMonth('service_date', now()->month)->count(),
            'active_devices'  => BiometricDevice::where('is_active', true)->count(),
            'income_month'    => Transaction::income()->thisMonth()->sum('amount'),
            'expense_month'   => Transaction::expense()->thisMonth()->sum('amount'),
            'total_groups'    => CellGroup::where('status', 'Active')->count(),
            'upcoming_events' => Event::whereIn('status', ['Published', 'Draft'])->where('start_date', '>=', now())->count(),
        ];

        $recentMembers  = Member::latest()->take(5)->get();
        $recentServices = ChurchService::withCount('attendanceLogs')->latest('service_date')->take(5)->get();
        $upcomingEvents = Event::where('start_date', '>=', now())->whereIn('status', ['Published', 'Ongoing'])->orderBy('start_date')->take(3)->get();

        return view('dashboard.super-admin', compact('stats', 'recentMembers', 'recentServices', 'upcomingEvents'));
    }

    private function pastorDashboard()
    {
        $stats = [
            'total_members'   => Member::count(),
            'active_members'  => Member::where('membership_status', 'Active')->count(),
            'income_month'    => Transaction::income()->thisMonth()->sum('amount'),
            'services_month'  => ChurchService::whereMonth('service_date', now()->month)->count(),
            'upcoming_events' => Event::whereIn('status', ['Published'])->where('start_date', '>=', now())->count(),
            'total_groups'    => CellGroup::where('status', 'Active')->count(),
        ];

        $recentServices = ChurchService::withCount('attendanceLogs')->latest('service_date')->take(6)->get();
        $upcomingEvents = Event::where('start_date', '>=', now())->whereIn('status', ['Published', 'Ongoing'])->orderBy('start_date')->take(5)->get();

        $monthlyAttendance = ChurchService::where('status', 'Completed')
            ->whereMonth('service_date', now()->month)
            ->withCount('attendanceLogs')
            ->get();

        return view('dashboard.pastor', compact('stats', 'recentServices', 'upcomingEvents', 'monthlyAttendance'));
    }

    private function hodDashboard()
    {
        $user = auth()->user();

        $myGroups = CellGroup::where('status', 'Active')->get();

        $stats = [
            'total_members'  => Member::count(),
            'active_members' => Member::where('membership_status', 'Active')->count(),
            'my_groups'      => $myGroups->count(),
            'services_month' => ChurchService::whereMonth('service_date', now()->month)->count(),
        ];

        $recentServices = ChurchService::withCount('attendanceLogs')->latest('service_date')->take(5)->get();

        return view('dashboard.hod', compact('stats', 'myGroups', 'recentServices'));
    }

    private function cellLeaderDashboard()
    {
        $user = auth()->user();

        $myGroups = CellGroup::where('status', 'Active')->with(['members', 'leader'])->get();

        $stats = [
            'my_groups'      => $myGroups->count(),
            'total_members'  => $myGroups->sum(fn($g) => $g->members->count()),
            'services_month' => ChurchService::whereMonth('service_date', now()->month)->count(),
            'upcoming_events' => Event::whereIn('status', ['Published'])->where('start_date', '>=', now())->count(),
        ];

        $upcomingEvents = Event::where('start_date', '>=', now())->whereIn('status', ['Published', 'Ongoing'])->orderBy('start_date')->take(3)->get();

        return view('dashboard.cell-leader', compact('stats', 'myGroups', 'upcomingEvents'));
    }

    private function dataEntryDashboard()
    {
        $stats = [
            'total_members'   => Member::count(),
            'active_members'  => Member::where('membership_status', 'Active')->count(),
            'visitors'        => Member::where('membership_status', 'Visitor')->count(),
            'added_today'     => Member::whereDate('created_at', today())->count(),
        ];

        $recentMembers = Member::latest()->take(10)->get();

        return view('dashboard.data-entry', compact('stats', 'recentMembers'));
    }

    private function usherDashboard()
    {
        $todayServices = ChurchService::whereDate('service_date', today())
            ->whereIn('status', ['Scheduled', 'Ongoing'])
            ->get();

        $recentServices = ChurchService::whereIn('status', ['Scheduled', 'Ongoing'])
            ->where('service_date', '>=', now()->subDays(3))
            ->latest('service_date')
            ->take(5)
            ->get();

        $stats = [
            'today_services'  => $todayServices->count(),
            'total_members'   => Member::where('membership_status', 'Active')->count(),
            'marked_today'    => AttendanceLog::whereDate('created_at', today())->count(),
        ];

        return view('dashboard.usher', compact('stats', 'todayServices', 'recentServices'));
    }
}