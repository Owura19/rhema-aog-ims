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
            transition: margin-left 0.3s ease;
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
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .topbar-title { font-size: 18px; font-weight: 700; color: var(--primary); }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--primary);
            cursor: pointer;
            padding: 6px;
            line-height: 1;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,37,64,0.5);
            z-index: 99;
        }
        .sidebar-overlay.show { display: block; }
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
            flex-wrap: wrap; gap: 12px;
        }
        .card-title { font-size: 15px; font-weight: 700; color: #1e293b; }
        .card-body { padding: 24px; }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            display: flex; align-items: center; gap: 14px;
            min-width: 0;
        }
        /* The text side of a stat card: may shrink, and is the size the amount scales to. */
        .stat-card > :last-child { flex: 1 1 auto; min-width: 0; container-type: inline-size; }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .stat-value {
            font-size: 26px; font-weight: 800; color: #1e293b; line-height: 1.15;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        /* Money on ONE line, sized to the card: about 11% of the text width, 11-24px. */
        .stat-value.stat-money { font-size: 18px; font-size: clamp(11px, 11cqi, 24px); }
        .stat-label { font-size: 13px; color: #64748b; margin-top: 4px; line-height: 1.3; }

        /* ── Shared responsive building blocks ─────────────────────────── */
        /* Stat cards in a grid are a size container. A narrow card (5 across on a laptop; the query
           measures the card's content box) puts its icon above the text, so the amount gets the card's
           full width and stays on one line at a readable size. flex-basis 0 keeps wider cards side by
           side; 100% makes the text wrap below the icon. */
        .stat-grid .stat-card { flex-wrap: wrap; container-type: inline-size; }
        .stat-grid .stat-card > :last-child { flex: 1 1 0; }
        @container (max-width: 170px) {
            .stat-grid .stat-card > :last-child { flex-basis: 100%; }
            .stat-grid .stat-icon { width: 40px; height: 40px; font-size: 17px; border-radius: 10px; }
        }
        /* A row of stat cards that wraps instead of overflowing. */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px; margin-bottom: 24px;
        }
        /* A row of header buttons that wraps onto a second line. */
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: flex-end; }
        .toolbar > form { display: contents; }
        /* Filter inputs that wrap neatly. */
        .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        /* Wide tables scroll inside their card, never the whole page. */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%; }
        .money { white-space: nowrap; }
        .only-phone { display: none; }
        /* width:100% must include padding and border, or full-width inputs/buttons spill out. */
        .form-control, .btn-primary, .btn-accent, .btn-outline, .btn-danger, .stat-card, .card { box-sizing: border-box; }
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

        /* Responsive grid helpers */
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .grid-main { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        /* Let grid columns shrink so wide tables scroll inside their card instead of pushing it off-screen. */
        .grid-4 > *, .grid-3 > *, .grid-2 > *, .grid-main > *, [style*="grid-template-columns"] > * { min-width: 0; }

        @media (max-width: 1024px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
            .grid-3 { grid-template-columns: repeat(2, 1fr); }
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
                box-shadow: 4px 0 24px rgba(0,0,0,0.25);   /* only while open: no shadow strip at the edge */
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-toggle {
                display: inline-flex;
                align-items: center;
            }
            .topbar {
                padding: 0 16px;
            }
            .topbar-title {
                font-size: 16px;
            }
            .page-content {
                padding: 18px 16px;
            }
            .grid-4, .grid-3, .grid-2, .grid-main {
                grid-template-columns: 1fr;
            }
            /* Catch-all: any inline grid with multiple columns (stat card
               rows, filter bars, etc. built without the .grid-N classes)
               collapses to a single column on mobile so nothing overflows. */
            [style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
            /* Tables in a scroll wrapper keep a readable width and scroll inside it;
               nothing else may push the page wider than the screen. */
            .table-wrap table, [style*="overflow-x:auto"] table, [style*="overflow-x: auto"] table {
                min-width: 600px;
            }
            .main-content { overflow-x: clip; }
            .topbar-user { display: none; }
        }

        /* Phones */
        @media (max-width: 640px) {
            .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px; margin-bottom: 18px; }
            .stat-grid .stat-card { flex-direction: column; align-items: flex-start; gap: 10px; padding: 14px; }
            .stat-grid .stat-card { flex-wrap: nowrap; }
            .stat-grid .stat-card > :last-child { width: 100%; flex: 0 0 auto; }
            .stat-grid .stat-icon { width: 36px; height: 36px; font-size: 16px; border-radius: 10px; }
            .stat-grid .stat-value { font-size: 20px; }
            .stat-grid .stat-value.stat-money { font-size: 15px; font-size: clamp(12px, 11cqi, 20px); }
            .stat-label { font-size: 12px; }

            .card-header { padding: 14px 16px; }
            .toolbar { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
            .toolbar > *, .toolbar > form > * { width: 100%; justify-content: center; }
            .toolbar .toolbar-main { grid-column: 1 / -1; order: -1; }

            .filter-bar { flex-direction: column; align-items: stretch !important; width: 100%; }
            .filter-bar > *, .filter-bar .form-control { width: 100% !important; }
            .filter-bar > .btn-primary, .filter-bar > .btn-outline { justify-content: center; }

            .only-phone { display: block; }
            .no-phone { display: none !important; }

            /* Tables marked .table-cards become one card per row. */
            .table-cards { min-width: 0 !important; }
            .table-cards thead { display: none; }
            .table-cards, .table-cards tbody, .table-cards tr, .table-cards td { display: block; width: 100%; box-sizing: border-box; }
            .table-cards tr {
                background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
                margin: 12px 0; padding: 12px 14px;
            }
            .table-cards td { border: none !important; padding: 3px 0 !important; background: transparent !important; }
            .table-cards td[data-label]::before {
                content: attr(data-label); display: block;
                font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px;
            }
            .table-cards td.empty-row { text-align: center; }
        }
        @media (max-width: 340px) {
            .stat-grid { grid-template-columns: 1fr !important; }
            .toolbar { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <h1 class="brand-font">Rhema <span>Assembly of God</span></h1>
        <p>Information Management System</p>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Main</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
    </div>

    @canany(['view members', 'view cell groups', 'view visitors'])
    <div class="sidebar-section">
        <div class="sidebar-section-label">People</div>
        @can('view members')
        <a href="{{ route('members.index') }}" class="sidebar-link {{ request()->routeIs('members.*') ? 'active' : '' }}">
            <i class="fas fa-users"></i> Members
        </a>

        <a href="{{ route('messages.inbox') }}" class="sidebar-link {{ request()->routeIs('messages.*') ? 'active' : '' }}">
            <i class="fas fa-comments"></i> Member Messages
        </a>
        @endcan
        @can('view visitors')
        <a href="{{ route('visitors.index') }}" class="sidebar-link {{ request()->routeIs('visitors.*') ? 'active' : '' }}">
            <i class="fas fa-user-friends"></i> Visitors
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
        @can('view devotions')
        <a href="{{ route('devotions.index') }}" class="sidebar-link {{ request()->routeIs('devotions.*') || request()->routeIs('devotion-themes.*') ? 'active' : '' }}">
            <i class="fas fa-book-open"></i> Devotions
        </a>
        @endcan
        @can('view sermons')
        <a href="{{ route('sermons.index') }}" class="sidebar-link {{ request()->routeIs('sermons.*') || request()->routeIs('sermon-series.*') ? 'active' : '' }}">
            <i class="fas fa-microphone"></i> Sermons
        </a>
        @endcan
        @can('manage announcements')
        <a href="{{ route('announcements.index') }}" class="sidebar-link {{ request()->routeIs('announcements.*') ? 'active' : '' }}">
            <i class="fas fa-bullhorn"></i> Announcements
        </a>
        @endcan
        @can('moderate community')
        <a href="{{ route('community-moderation.index') }}" class="sidebar-link {{ request()->routeIs('community-moderation.*') ? 'active' : '' }}">
            <i class="fas fa-shield-halved"></i> Community Moderation
        </a>
        @endcan
        {{-- RandyImpact is open to any logged-in user. (The old Community page is retired.) --}}
        <a href="{{ route('randyimpact.index') }}" class="sidebar-link {{ request()->routeIs('randyimpact.*') ? 'active' : '' }}">
            <i class="fas fa-bolt"></i> Dr Patrick AI
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

    @can('view pledges')
    @cannot('view finance')
    <div class="sidebar-section">
        <div class="sidebar-section-label">Finance</div>
        <a href="{{ route('pledges.index') }}" class="sidebar-link {{ request()->routeIs('pledges.*') ? 'active' : '' }}">
            <i class="fas fa-hand-holding-heart"></i> Pledges
        </a>
        <a href="{{ route('payments.index') }}" class="sidebar-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
            <i class="fas fa-receipt"></i> Payments
        </a>
        <a href="{{ route('pledges.summary') }}" class="sidebar-link {{ request()->routeIs('pledges.summary') ? 'active' : '' }}">
            <i class="fas fa-chart-pie"></i> Pledge Summary
        </a>
        <a href="{{ route('harvests.index') }}" class="sidebar-link {{ request()->routeIs('harvests.*') ? 'active' : '' }}">
            <i class="fas fa-wheat-awn"></i> Harvest
        </a>
        @canany(['view day-born offering', 'record day-born offering'])
        <a href="{{ route(auth()->user()->can('view day-born offering') ? 'day-born.index' : 'day-born.create') }}" class="sidebar-link {{ request()->routeIs('day-born.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-week"></i> Day-born Offering
        </a>
        @endcanany
    </div>
    @endcannot
    @endcan
    {{-- Day-born offering for those with no other finance pages (e.g. ushers) --}}
    @canany(['view day-born offering', 'record day-born offering'])
    @if(! auth()->user()->can('view finance') && ! auth()->user()->can('view pledges'))
    <div class="sidebar-section">
        <div class="sidebar-section-label">Finance</div>
        <a href="{{ route(auth()->user()->can('view day-born offering') ? 'day-born.index' : 'day-born.create') }}" class="sidebar-link {{ request()->routeIs('day-born.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-week"></i> Day-born Offering
        </a>
    </div>
    @endif
    @endcanany
    @can('view finance')
    <div class="sidebar-section">
        <div class="sidebar-section-label">Finance</div>
        <a href="{{ route('finance.index') }}" class="sidebar-link {{ request()->routeIs('finance.index') || request()->routeIs('finance.show') || request()->routeIs('finance.create') || request()->routeIs('finance.edit') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave"></i> Transactions
        </a>

        <a href="{{ route('vouchers.index') }}" class="sidebar-link {{ request()->routeIs('vouchers.*') ? 'active' : '' }}">
            <i class="fas fa-file-invoice-dollar"></i> Payment Vouchers
        </a>
        <a href="{{ route('bank.index') }}" class="sidebar-link {{ request()->routeIs('bank.*') ? 'active' : '' }}">
            <i class="fas fa-building-columns"></i> Bank Transactions
        </a>
        <a href="{{ route('finance.report') }}" class="sidebar-link {{ request()->routeIs('finance.report') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i> Finance Reports
        </a>
        <a href="{{ route('pledges.index') }}" class="sidebar-link {{ request()->routeIs('pledges.*') ? 'active' : '' }}">
            <i class="fas fa-hand-holding-heart"></i> Pledges
        </a>
        <a href="{{ route('payments.index') }}" class="sidebar-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
            <i class="fas fa-receipt"></i> Payments
        </a>
        <a href="{{ route('pledges.summary') }}" class="sidebar-link {{ request()->routeIs('pledges.summary') ? 'active' : '' }}">
            <i class="fas fa-chart-pie"></i> Pledge Summary
        </a>
        <a href="{{ route('harvests.index') }}" class="sidebar-link {{ request()->routeIs('harvests.*') ? 'active' : '' }}">
            <i class="fas fa-wheat-awn"></i> Harvest
        </a>
        @canany(['view day-born offering', 'record day-born offering'])
        <a href="{{ route(auth()->user()->can('view day-born offering') ? 'day-born.index' : 'day-born.create') }}" class="sidebar-link {{ request()->routeIs('day-born.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-week"></i> Day-born Offering
        </a>
        @endcanany
        <a href="{{ route('accounts.index') }}" class="sidebar-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
            <i class="fas fa-sitemap"></i> Chart of Accounts
        </a>
        <a href="{{ route('finance.reports-hub') }}" class="sidebar-link {{ request()->routeIs('finance.reports-hub') || request()->routeIs('finance.statement') || request()->routeIs('finance.income-note') || request()->routeIs('finance.expenditure-note') || request()->routeIs('finance.budget.report') ? 'active' : '' }}">
            <i class="fas fa-chart-pie"></i> Financial Reports
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

<!-- Mobile overlay (tap to close sidebar) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Main Content -->
<div class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="topbar-title">@yield('title', 'Dashboard')</div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user" style="text-align:right;">
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

        @if(session('warning'))
            <div class="alert alert-warning">
                <i class="fas fa-triangle-exclamation"></i> {{ session('warning') }}
            </div>
        @endif

        @yield('content')
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('show');
    }
    // On mobile, close the menu after tapping a navigation link
    document.querySelectorAll('.sidebar-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });
</script>

</body>
</html>