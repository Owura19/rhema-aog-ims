<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard — {{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
    :root{--primary:#1a3c5e;--accent:#e8a020;--ink:#0f172a;--ink-2:#64748b;--line:#e2e8f0;}
    body{background:#f6f7f9;color:var(--ink);}
    .topbar{background:linear-gradient(135deg,#1a3c5e,#0f2540);color:#fff;padding:0 28px;height:66px;display:flex;align-items:center;justify-content:space-between;}
    .topbar .brand{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;}
    .topbar .brand span{color:var(--accent);}
    .topbar .right{display:flex;align-items:center;gap:16px;}
    .topbar a.logout{color:rgba(255,255,255,.8);text-decoration:none;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;}
    .topbar a.logout:hover{color:#fff;}
    .avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;}

    .wrap{max-width:920px;margin:0 auto;padding:28px 20px;}
    .hero{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px 30px;margin-bottom:22px;box-shadow:0 1px 3px rgba(16,24,40,.05);}
    .hero h1{font-size:22px;font-weight:800;letter-spacing:-.4px;}
    .hero p{color:var(--ink-2);font-size:14px;margin-top:6px;}

    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:22px;}
    @media(max-width:720px){.grid{grid-template-columns:1fr;}}
    .stat{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(16,24,40,.05);}
    .stat-ic{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:12px;}
    .stat-v{font-size:22px;font-weight:800;letter-spacing:-.5px;}
    .stat-l{font-size:12.5px;color:var(--ink-2);margin-top:4px;}

    .card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 1px 3px rgba(16,24,40,.05);margin-bottom:22px;}
    .card-head{padding:18px 22px;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px;}
    table{width:100%;border-collapse:collapse;}
    th{background:#fafbfc;padding:11px 20px;text-align:left;font-size:11.5px;font-weight:700;color:var(--ink-2);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--line);}
    td{padding:12px 20px;font-size:13.5px;color:var(--ink-2);border-bottom:1px solid #f1f5f9;}
    tr:last-child td{border-bottom:none;}
    .badge{padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
    .b-green{background:#dcfce7;color:#16a34a;}.b-blue{background:#dbeafe;color:#2563eb;}.b-gray{background:#f1f5f9;color:#64748b;}
    .empty{text-align:center;color:#94a3b8;padding:26px;font-size:13px;}
    .bar{background:#f1f5f9;border-radius:20px;height:7px;overflow:hidden;margin-top:6px;}
    .bar-fill{height:7px;background:#16a34a;border-radius:20px;}
</style>
</head>
<body>

<div class="topbar">
    <div class="brand">{{ config('app.name') }} <span>Member Portal</span></div>
    <div class="right">
        <div class="avatar">{{ strtoupper(substr($member->first_name,0,1)) }}</div>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit" class="logout" style="background:none;border:none;cursor:pointer;"><i class="fas fa-right-from-bracket"></i> Sign out</button>
        </form>
    </div>
</div>

<div class="wrap">

    <div class="hero">
        <h1>Hello, {{ $member->first_name }} 👋</h1>
        <p>Welcome to your member portal. Here you can see your giving, pledges, and personal details.</p>
        <a href="{{ route('portal.messages') }}" style="display:inline-flex;align-items:center;gap:8px;margin-top:14px;background:#1a3c5e;color:#fff;padding:10px 16px;border-radius:9px;text-decoration:none;font-size:13.5px;font-weight:600;">
            <i class="fas fa-comments"></i> Message Leadership
        </a>
    </div>

    <div class="grid">
        <div class="stat">
            <div class="stat-ic" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-hand-holding-heart"></i></div>
            <div class="stat-v">GHS {{ number_format($givingThisYear, 2) }}</div>
            <div class="stat-l">Your Giving in {{ $year }}</div>
        </div>
        <div class="stat">
            <div class="stat-ic" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-piggy-bank"></i></div>
            <div class="stat-v">GHS {{ number_format($givingAllTime, 2) }}</div>
            <div class="stat-l">Total Giving (All Time)</div>
        </div>
        <div class="stat">
            <div class="stat-ic" style="background:#f3e8ff;color:#7c3aed;"><i class="fas fa-file-signature"></i></div>
            <div class="stat-v">{{ $activePledges->count() }}</div>
            <div class="stat-l">Active Pledges</div>
        </div>
    </div>

    <!-- Giving breakdown by type -->
    <div class="card">
        <div class="card-head"><i class="fas fa-chart-pie" style="color:#2563eb;"></i> My Giving Breakdown</div>
        <div style="overflow-x:auto;">
            <table>
                <thead><tr><th>Giving Type</th><th>Times Given</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody>
                    @forelse($givingByType as $row)
                    <tr>
                        <td style="font-weight:600;color:var(--ink);">{{ $row->type }}</td>
                        <td>{{ $row->count }}</td>
                        <td style="text-align:right;font-weight:600;color:#16a34a;">GHS {{ number_format($row->total, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="empty">No giving recorded yet.</td></tr>
                    @endforelse
                    @if($givingByType->isNotEmpty())
                    <tr style="border-top:2px solid #e2e8f0;">
                        <td colspan="2" style="font-weight:800;color:var(--ink);">Total</td>
                        <td style="text-align:right;font-weight:800;color:#16a34a;">GHS {{ number_format($givingByType->sum('total'), 2) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pledges -->
    <div class="card">
        <div class="card-head"><i class="fas fa-file-signature" style="color:#7c3aed;"></i> My Pledges</div>
        <div style="overflow-x:auto;">
            <table>
                <thead><tr><th>Purpose</th><th>Pledged</th><th>Paid</th><th>Progress</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($pledges as $pledge)
                    <tr>
                        <td style="font-weight:600;color:var(--ink);">{{ optional($pledge->purpose)->name ?? 'Pledge' }}</td>
                        <td>GHS {{ number_format($pledge->amount_pledged, 2) }}</td>
                        <td style="color:#16a34a;">GHS {{ number_format($pledge->total_paid, 2) }}</td>
                        <td style="min-width:110px;">
                            <div class="bar"><div class="bar-fill" style="width:{{ $pledge->progress_percent }}%;"></div></div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:3px;">{{ $pledge->progress_percent }}%</div>
                        </td>
                        <td>
                            @if($pledge->status === 'Fulfilled')
                                <span class="badge b-green">Fulfilled</span>
                            @elseif($pledge->status === 'Active')
                                <span class="badge b-blue">Active</span>
                            @else
                                <span class="badge b-gray">{{ $pledge->status }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="empty">You have no pledges on record.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent giving -->
    <div class="card">
        <div class="card-head"><i class="fas fa-receipt" style="color:#16a34a;"></i> Recent Giving</div>
        <div style="overflow-x:auto;">
            <table>
                <thead><tr><th>Date</th><th>Type</th><th>Amount</th></tr></thead>
                <tbody>
                    @forelse($recentGiving as $tx)
                    <tr>
                        <td>{{ $tx->transaction_date->format('M d, Y') }}</td>
                        <td><span class="badge b-gray">{{ $tx->type }}</span></td>
                        <td style="font-weight:600;color:#16a34a;">GHS {{ number_format($tx->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="empty">No giving records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="text-align:center;color:#94a3b8;font-size:12px;padding:10px 0 30px;">
        {{ config('app.name') }} — Member Portal
    </div>

</div>
</body>
</html>