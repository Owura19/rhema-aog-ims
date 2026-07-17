@extends('layouts.app')

@section('title', 'Financial Reports')

@section('content')

<div style="margin-bottom:20px;">
    <p style="color:#64748b; font-size:14px;">Select a report to generate and view detailed financial information for any date range.</p>
</div>

<div class="grid-3">

    <!-- Income & Expenditure Statement -->
    <a href="{{ route('finance.statement') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#dbeafe; margin-bottom:14px;">
                <i class="fas fa-file-invoice-dollar" style="color:#2563eb;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Income &amp; Expenditure Statement</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Summary of all inflows and outflows by account group, with the net balance for a selected period.</div>
            <div style="color:#2563eb; font-size:13px; font-weight:600; margin-top:14px;">Open report <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Finance Analytics -->
    <a href="{{ route('finance.analytics') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#dcfce7; margin-bottom:14px;">
                <i class="fas fa-chart-pie" style="color:#16a34a;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Finance Analytics</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Visual dashboard — income vs expenditure trends and giving breakdown by type, with charts.</div>
            <div style="color:#16a34a; font-size:13px; font-weight:600; margin-top:14px;">Open dashboard <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Income Note -->
    <a href="{{ route('finance.income-note') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#dcfce7; margin-bottom:14px;">
                <i class="fas fa-arrow-up" style="color:#16a34a;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Income Note</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Detailed breakdown of every income sub-account with group subtotals and total income.</div>
            <div style="color:#16a34a; font-size:13px; font-weight:600; margin-top:14px;">Open report <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Expenditure Note -->
    <a href="{{ route('finance.expenditure-note') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#fee2e2; margin-bottom:14px;">
                <i class="fas fa-arrow-down" style="color:#dc2626;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Expenditure Note</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Detailed breakdown of every expense sub-account with group subtotals and total expenditure.</div>
            <div style="color:#dc2626; font-size:13px; font-weight:600; margin-top:14px;">Open report <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Budget vs Actual -->
    <a href="{{ route('finance.budget.report') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#fef9c3; margin-bottom:14px;">
                <i class="fas fa-bullseye" style="color:#ca8a04;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Budget vs Actual</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Compare budgeted figures against actual income and expenditure, with variance per group.</div>
            <div style="color:#ca8a04; font-size:13px; font-weight:600; margin-top:14px;">Open report <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Trial Balance -->
    <a href="{{ route('finance.trial-balance') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#dbeafe; margin-bottom:14px;">
                <i class="fas fa-scale-balanced" style="color:#2563eb;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Trial Balance</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Every account with its debit and credit balances from the ledger, proving the books balance.</div>
            <div style="color:#2563eb; font-size:13px; font-weight:600; margin-top:14px;">Open report <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Balance Sheet -->
    <a href="{{ route('finance.balance-sheet') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#f3e8ff; margin-bottom:14px;">
                <i class="fas fa-building-columns" style="color:#7c3aed;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Balance Sheet</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Statement of financial position — assets, liabilities, and equity at a point in time.</div>
            <div style="color:#7c3aed; font-size:13px; font-weight:600; margin-top:14px;">Open report <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Journal Entries -->
    <a href="{{ route('finance.journals.index') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#ede9fe; margin-bottom:14px;">
                <i class="fas fa-book" style="color:#7c3aed;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Journal Entries</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">View all ledger entries and post manual journals to correct mistakes or record adjustments.</div>
            <div style="color:#7c3aed; font-size:13px; font-weight:600; margin-top:14px;">Open <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Chart of Accounts -->
    <a href="{{ route('accounts.index') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#f3e8ff; margin-bottom:14px;">
                <i class="fas fa-sitemap" style="color:#7c3aed;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Chart of Accounts</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">The full list of income and expenditure accounts and their reference codes.</div>
            <div style="color:#7c3aed; font-size:13px; font-weight:600; margin-top:14px;">View chart <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- General Ledger -->
    <a href="{{ route('ledger.index') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#e0f2fe; margin-bottom:14px;">
                <i class="fas fa-book-open" style="color:#0284c7;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">General Ledger</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Every account with its debits, credits, and balance. Drill into any account for its full statement.</div>
            <div style="color:#0284c7; font-size:13px; font-weight:600; margin-top:14px;">Open ledger <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

    <!-- Member Giving Report -->
    <a href="{{ route('member-ledger.index') }}" class="card" style="text-decoration:none; display:block; transition:box-shadow 0.2s;">
        <div class="card-body">
            <div class="stat-icon" style="background:#dcfce7; margin-bottom:14px;">
                <i class="fas fa-hand-holding-heart" style="color:#16a34a;"></i>
            </div>
            <div style="font-weight:700; color:#1e293b; font-size:16px; margin-bottom:6px;">Member Giving Report</div>
            <div style="color:#64748b; font-size:13px; line-height:1.5;">Each member's total giving, with drill-down to individual printable giving statements.</div>
            <div style="color:#16a34a; font-size:13px; font-weight:600; margin-top:14px;">Open report <i class="fas fa-arrow-right" style="font-size:11px;"></i></div>
        </div>
    </a>

</div>

@endsection