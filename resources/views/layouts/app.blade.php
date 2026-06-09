<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — @yield('title', 'Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .brand-font { font-family: 'Plus Jakarta Sans', sans-serif; }
        :root {
            --primary: #1a3c5e;
            --primary-light: #234d78;
            --accent: #e8a020;
            --accent-light: #f5b942;
            --sidebar-width: 260px;
            --topbar-height: 65px;
        }
        body { background: #f0f4f8; }
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a3c5e 0%, #0f2540 100%);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        .sidebar-logo {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-logo h1 {
            color: #fff;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.2;
        }
        .sidebar-logo span { color: var(--accent); }
        .sidebar-logo p {
            color: rgba(255,255,255,0.5);
            font-size: 11px;
            margin-top: 2px;
        }
        .sidebar-section { padding: 20px 0 8px; }
        .sidebar-section-label {
            color: rgba(255,255,255,0.35);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            padding: 0 24px;
            margin-bottom: 6px;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 24px;
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.08);
            border-left-color: rgba(255,255,255,0.3);
        }
        .sidebar-link.active {
            color: #fff;
            background: rgba(232,160,32,0.15);
            border-left-color: var(--accent);
        }
        .sidebar-link i { width: 18px; text-align: center; font-size: 15px; }
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        .topbar {
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-title { font-size: 18px; font-weight: 700; color: var(--primary); }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .user-avatar {
            width: 36px; height: 36px;
            background: var(--primary);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 14px; font-weight: 600;
        }
        .page-content { padding: 28px; }
        .card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-title { font-size: 15px; font-weight: 700; color: #1e293b; }
        .card-body { padding: 24px; }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px 24px;
            border: 1px solid #e2e8f0;
            display: flex; align-items: center; gap: 16px;
        }
        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        .stat-value { font-size: 26px; font-weight: 800; color: #1e293b; line-height: 1; }
        .stat-label { font-size: 13px; color: #64748b; margin-top: 3px; }
        .btn-primary {
            background: var(--primary); color: #fff;
            padding: 9px 18px; border-radius: 8px;
            font-size: 14px; font-weight: 600; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-primary:hover { background: var(--primary-light); color: #fff; }
        .btn-accent {
            background: var(--accent); color: #fff;
            padding: 9px 18px; border-radius: 8px;
            font-size: 14px; font-weight: 600; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-accent:hover { background: var(--accent-light); color: #fff; }
        .btn-outline {
            background: transparent; color: var(--primary);
            padding: 9px 18px; border-radius: 8px;
            font-size: 14px; font-weight: 600;
            border: 2px solid var(--primary); cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-outline:hover { background: var(--primary); color: #fff; }
        .btn-danger {
            background: #ef4444; color: #fff;
            padding: 9px 18px; border-radius: 8px;
            font-size: 14px; font-weight: 600; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-danger:hover { background: #dc2626; color: #fff; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .badge {
            display: inline-flex; align-items: center;
            padding: 3px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-warning { background: #fef9c3; color: #ca8a04; }
        .badge-danger  { background: #fee2e2; color: #dc2626; }
        .badge-info    { background: #dbeafe; color: #2563eb; }
        .badge-gray    { background: #f1f5f9; color: #64748b; }
        .form-label {
            display: block; font-size: 13px; font-weight: 600;
            color: #374151; margin-bottom: 6px;
        }
        .form-control {
            width: 100%; padding: 9px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; color: #1e293b; background: #fff;
            transition: border-color 0.2s; outline: none;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,60,94,0.08);
        }
        .form-control.is-invalid { border-color: #ef4444; }
        .invalid-feedback { color: #ef4444; font-size: 12px; margin-top: 4px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th {
            background: #f8fafc; padding: 12px 16px; text-align: left;
            font-size: 12px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .table td {
            padding: 14px 16px; font-size: 14px; color: #374151;
            border-bottom: 1px solid #f1f5f9; vertical-align: middle;
        }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #f8fafc; }
        .alert {
            padding: 12px 18px; border-radius: 8px; font-size: 14px;
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-danger  { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-warning { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
        .member-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid #e2e8f0;
        }
        .member-avatar-placeholder {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--primary);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 14px; font-weight: 700;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-logo">
        <h1 class="brand-font">GraceWorld <span>IMS</span></h1>
        <p>International Management System</p>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Main</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
    </div>

    @canany(['view members', 'view cell groups'])
    <div class="sidebar-section">
        <div class="sidebar-section-label">People</div>
        @can('view members')
        <a href="{{ route('members.index') }}" class="sidebar-link {{ request()->routeIs('members.*') ? 'active' : '' }}">
            <i class="fas fa-users"></i> Members
        </a>
        @endcan
        @can('view cell groups')
        <a href="{{ route('cellgroups.index') }}" class="sidebar-link {{ request()->routeIs('cellgroups.*') ? 'active' : '' }}">
            <i class="fas fa-home"></i> Cell Groups
        </a>
        @endcan
    </div>
    @endcanany

    <div class="sidebar-section">
        <div class="sidebar-section-label">Programs</div>
        @can('view events')
        <a href="{{ route('events.index') }}" class="sidebar-link {{ request()->routeIs('events.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt"></i> Events & Programs
        </a>
        @endcan
        {{-- Community & RandyImpact are open to any logged-in user --}}
        <a href="{{ route('community.index') }}" class="sidebar-link {{ request()->routeIs('community.*') ? 'active' : '' }}">
            <i class="fas fa-globe"></i> Community
        </a>
        <a href="{{ route('randyimpact.index') }}" class="sidebar-link {{ request()->routeIs('randyimpact.*') ? 'active' : '' }}">
            <i class="fas fa-bolt"></i> RandyImpact AI
        </a>
    </div>

    @can('view attendance')
    <div class="sidebar-section">
        <div class="sidebar-section-label">Attendance</div>
        <a href="{{ route('services.index') }}" class="sidebar-link {{ request()->routeIs('services.*') ? 'active' : '' }}">
            <i class="fas fa-church"></i> Church Services
        </a>
        <a href="{{ route('attendance.index') }}" class="sidebar-link {{ request()->routeIs('attendance.index') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list"></i> Attendance Logs
        </a>
        <a href="{{ route('attendance.report') }}" class="sidebar-link {{ request()->routeIs('attendance.report') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i> Att. Reports
        </a>
        @role('Super Admin')
        <a href="{{ route('devices.index') }}" class="sidebar-link {{ request()->routeIs('devices.*') ? 'active' : '' }}">
            <i class="fas fa-fingerprint"></i> Biometric Devices
        </a>
        @endrole
    </div>
    @endcan

    @can('view finance')
    <div class="sidebar-section">
        <div class="sidebar-section-label">Finance</div>
        <a href="{{ route('finance.index') }}" class="sidebar-link {{ request()->routeIs('finance.index') || request()->routeIs('finance.show') || request()->routeIs('finance.create') || request()->routeIs('finance.edit') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave"></i> Transactions
        </a>
        <a href="{{ route('finance.report') }}" class="sidebar-link {{ request()->routeIs('finance.report') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i> Finance Reports
        </a>
    </div>
    @endcan

    @can('manage users')
    <div class="sidebar-section">
        <div class="sidebar-section-label">Administration</div>
        <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i class="fas fa-user-shield"></i> User Management
        </a>
    </div>
    @endcan

    <div class="sidebar-section">
        <div class="sidebar-section-label">Account</div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-link" style="width:100%; background:none; border:none; cursor:pointer; text-align:left;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">@yield('title', 'Dashboard')</div>
        <div class="topbar-right">
            <div style="text-align:right;">
                <div style="font-size:14px; font-weight:600; color:#1e293b;">{{ auth()->user()->name }}</div>
                <div style="font-size:12px; color:#64748b;">{{ auth()->user()->getRoleNames()->first() }}</div>
            </div>
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
        </div>
    </div>

    <div class="page-content">
        @if(session('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </div>
</div>

</body>
</html>