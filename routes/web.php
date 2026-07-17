<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ChurchServiceController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BiometricDeviceController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\CellGroupController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\RandyImpactAIController;
use App\Http\Controllers\VisitorController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {

    // Dashboard — any logged-in user
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ─── MEMBER PORTAL (member's own data only) ──────────────────
    Route::prefix('portal')->group(function () {
        Route::get('/', [\App\Http\Controllers\MemberPortalController::class, 'dashboard'])->name('portal.dashboard');
        Route::get('/giving', [\App\Http\Controllers\MemberPortalController::class, 'giving'])->name('portal.giving');
        Route::get('/profile', [\App\Http\Controllers\MemberPortalController::class, 'profile'])->name('portal.profile');
        Route::get('/messages', [\App\Http\Controllers\MemberMessageController::class, 'memberThread'])->name('portal.messages');
        Route::post('/messages', [\App\Http\Controllers\MemberMessageController::class, 'memberSend'])->name('portal.messages.send');
    });

    // ─────────────────────────────────────────────────────────────
    // MEMBERS — view: all | create/edit: Super Admin + Data Entry | delete: Super Admin
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:create members')->group(function () {
        Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
    });
    Route::middleware('permission:edit members')->group(function () {
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('/members/{member}/portal-login', [\App\Http\Controllers\MemberPortalAccessController::class, 'create'])->name('members.portal.create');
        Route::post('/members/{member}/portal-reset', [\App\Http\Controllers\MemberPortalAccessController::class, 'resetPassword'])->name('members.portal.reset');
        Route::delete('/members/{member}/portal-login', [\App\Http\Controllers\MemberPortalAccessController::class, 'revoke'])->name('members.portal.revoke');
        Route::patch('/members/{member}', [MemberController::class, 'update']);
    });
    Route::middleware('permission:delete members')->group(function () {
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    });
    Route::middleware('permission:view members')->group(function () {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');

    // ─── MEMBER MESSAGES (leadership side) ───────────────────────
    Route::middleware('permission:view members')->group(function () {
        Route::get('/member-messages', [\App\Http\Controllers\MemberMessageController::class, 'inbox'])->name('messages.inbox');
        Route::get('/member-messages/{member}', [\App\Http\Controllers\MemberMessageController::class, 'show'])->name('messages.thread');
        Route::post('/member-messages/{member}/reply', [\App\Http\Controllers\MemberMessageController::class, 'reply'])->name('messages.reply');
    });
    });

    // ─────────────────────────────────────────────────────────────
    // CHURCH SERVICES — manage: Super Admin, Pastor, HOD (role-gated)
    // Services are leadership records; marking attendance against them is separate.
    // ─────────────────────────────────────────────────────────────
    Route::middleware('role:Super Admin|Pastor|HOD')->group(function () {
        Route::get('/services/create', [ChurchServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ChurchServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}/edit', [ChurchServiceController::class, 'edit'])->name('services.edit');
        Route::put('/services/{service}', [ChurchServiceController::class, 'update'])->name('services.update');
        Route::patch('/services/{service}', [ChurchServiceController::class, 'update']);
        Route::delete('/services/{service}', [ChurchServiceController::class, 'destroy'])->name('services.destroy');
    });
    // Viewing services — anyone who can manage attendance needs to see them
    Route::middleware('permission:view attendance')->group(function () {
        Route::get('/services', [ChurchServiceController::class, 'index'])->name('services.index');
        Route::get('/services/{service}', [ChurchServiceController::class, 'show'])->name('services.show');
    });

    // ─────────────────────────────────────────────────────────────
    // ATTENDANCE — view: 'view attendance' | actions: 'manage attendance'
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:view attendance')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');
    });
    Route::middleware('permission:manage attendance')->group(function () {
        Route::post('/attendance/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');
        Route::post('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk-mark');
        Route::post('/attendance/sync-biometric', [AttendanceController::class, 'syncBiometric'])->name('attendance.sync-biometric');
        Route::delete('/attendance/{attendanceLog}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
    });

    // ─────────────────────────────────────────────────────────────
    // BIOMETRIC DEVICES — Super Admin only (controls attendance integrity)
    // ─────────────────────────────────────────────────────────────
    Route::middleware('role:Super Admin')->group(function () {
        Route::resource('devices', BiometricDeviceController::class);
        Route::post('/devices/{device}/test', [BiometricDeviceController::class, 'testConnection'])->name('devices.test');
    });

    // ─────────────────────────────────────────────────────────────
    // FINANCE — view: Super Admin + Pastor | create/edit/delete: Super Admin
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:create finance')->group(function () {
        Route::get('/finance/create', [FinanceController::class, 'create'])->name('finance.create');
        Route::post('/finance', [FinanceController::class, 'store'])->name('finance.store');
    });
    Route::middleware('permission:edit finance')->group(function () {
        Route::get('/finance/{finance}/edit', [FinanceController::class, 'edit'])->name('finance.edit');
        Route::put('/finance/{finance}', [FinanceController::class, 'update'])->name('finance.update');
        Route::patch('/finance/{finance}', [FinanceController::class, 'update']);
    });
    Route::middleware('permission:delete finance')->group(function () {
        Route::delete('/finance/{finance}', [FinanceController::class, 'destroy'])->name('finance.destroy');
    });
    Route::middleware('permission:view finance')->group(function () {
        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/finance-report', [FinanceController::class, 'report'])->name('finance.report');
        Route::get('/finance-export', [FinanceController::class, 'exportExcel'])->name('finance.export');
        Route::get('/finance/{finance}', [FinanceController::class, 'show'])->name('finance.show');
        Route::get('/receipts/{transaction}', [FinanceController::class, 'receiptView'])->name('finance.receipt');
        Route::get('/receipts/{transaction}/download', [FinanceController::class, 'receipt'])->name('finance.receipt.download');
        Route::get('/receipts/{transaction}/print', [FinanceController::class, 'print'])->name('finance.receipt.print');
    });

    // ─────────────────────────────────────────────────────────────
    // CELL GROUPS — view: 'view cell groups' | manage: 'manage cell groups'
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:manage cell groups')->group(function () {
        Route::get('/cellgroups/create', [CellGroupController::class, 'create'])->name('cellgroups.create');
        Route::post('/cellgroups', [CellGroupController::class, 'store'])->name('cellgroups.store');
        Route::get('/cellgroups/{cellgroup}/edit', [CellGroupController::class, 'edit'])->name('cellgroups.edit');
        Route::put('/cellgroups/{cellgroup}', [CellGroupController::class, 'update'])->name('cellgroups.update');
        Route::patch('/cellgroups/{cellgroup}', [CellGroupController::class, 'update']);
        Route::delete('/cellgroups/{cellgroup}', [CellGroupController::class, 'destroy'])->name('cellgroups.destroy');
        Route::post('/cellgroups/{cellgroup}/members', [CellGroupController::class, 'addMember'])->name('cellgroups.add-member');
        Route::delete('/cellgroups/{cellgroup}/members/{member}', [CellGroupController::class, 'removeMember'])->name('cellgroups.remove-member');
    });
    Route::middleware('permission:view cell groups')->group(function () {
        Route::get('/cellgroups', [CellGroupController::class, 'index'])->name('cellgroups.index');
        Route::get('/cellgroups/{cellgroup}', [CellGroupController::class, 'show'])->name('cellgroups.show');
    });

    // ─────────────────────────────────────────────────────────────
    // EVENTS — view: 'view events' | create/edit/delete: matching permission
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:create events')->group(function () {
        Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
    });
    Route::middleware('permission:edit events')->group(function () {
        Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::patch('/events/{event}', [EventController::class, 'update']);
    });
    Route::middleware('permission:delete events')->group(function () {
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    });
    Route::middleware('permission:view events')->group(function () {
        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        // RSVP — any viewer can RSVP / cancel their own
        Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('events.rsvp');
        Route::delete('/events/{event}/rsvp/{rsvp}', [EventController::class, 'cancelRsvp'])->name('events.cancel-rsvp');
    });

    // ─────────────────────────────────────────────────────────────
    // VISITORS — view: 'view visitors' | manage: 'manage visitors'
    // Follow-up tracking with one-click conversion to member.
    // ─────────────────────────────────────────────────────────────
    Route::resource('visitors', VisitorController::class);
    Route::post('/visitors/{visitor}/convert', [VisitorController::class, 'convertToMember'])->name('visitors.convert');

    // ─────────────────────────────────────────────────────────────
    // USER MANAGEMENT — Super Admin only ('manage users')
    // Staff accounts: create, edit, activate/deactivate, delete.
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:manage users')->group(function () {
        Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])->name('users.create');
        Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-active', [\App\Http\Controllers\UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
    });

    // ─────────────────────────────────────────────────────────────
    // COMMUNITY — any logged-in user
    // ─────────────────────────────────────────────────────────────
    Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
    Route::post('/community', [CommunityController::class, 'store'])->name('community.store');
    Route::delete('/community/{post}', [CommunityController::class, 'destroy'])->name('community.destroy');
    Route::post('/community/{post}/like', [CommunityController::class, 'like'])->name('community.like');
    Route::post('/community/{post}/comment', [CommunityController::class, 'comment'])->name('community.comment');
    Route::delete('/community/comments/{comment}', [CommunityController::class, 'deleteComment'])->name('community.delete-comment');
    Route::post('/community/{post}/pin', [CommunityController::class, 'pin'])->name('community.pin');

    // ─────────────────────────────────────────────────────────────
    // RANDYIMPACT AI — any logged-in user
    // ─────────────────────────────────────────────────────────────
    Route::get('/randyimpact', [RandyImpactAIController::class, 'index'])->name('randyimpact.index');
    Route::get('/randyimpact/live-sermon', [RandyImpactAIController::class, 'liveSermon'])->name('randyimpact.live-sermon');
    Route::get('/randyimpact/projector', [RandyImpactAIController::class, 'projector'])->name('randyimpact.projector');
    Route::post('/randyimpact/get-verse', [RandyImpactAIController::class, 'getVerse'])->name('randyimpact.get-verse');
    Route::post('/randyimpact/detect-verses', [RandyImpactAIController::class, 'detectVerses'])->name('randyimpact.detect-verses');
    Route::post('/randyimpact/generate-notes', [RandyImpactAIController::class, 'generateNotes'])->name('randyimpact.generate-notes');
    Route::post('/randyimpact/ask-bible', [RandyImpactAIController::class, 'askBible'])->name('randyimpact.ask-bible');
    Route::post('/randyimpact/generate-summary', [RandyImpactAIController::class, 'generateSummary'])->name('randyimpact.generate-summary');


    // ─────────────────────────────────────────────────────────────
    // PLEDGES — view: view finance | manage: create finance
    // ─────────────────────────────────────────────────────────────
    Route::get('/pledges', [\App\Http\Controllers\PledgeController::class, 'index'])->name('pledges.index');
    Route::get('/pledges/create', [\App\Http\Controllers\PledgeController::class, 'create'])->name('pledges.create');
    Route::post('/pledges', [\App\Http\Controllers\PledgeController::class, 'store'])->name('pledges.store');
    Route::get('/pledges/{pledge}', [\App\Http\Controllers\PledgeController::class, 'show'])->name('pledges.show');
    Route::post('/pledges/{pledge}/payments', [\App\Http\Controllers\PledgeController::class, 'storePayment'])->name('pledges.payments.store');
    Route::patch('/pledges/{pledge}/cancel', [\App\Http\Controllers\PledgeController::class, 'cancel'])->name('pledges.cancel');
    Route::delete('/pledges/{pledge}', [\App\Http\Controllers\PledgeController::class, 'destroy'])->name('pledges.destroy');

    // Member family relationships
    Route::post('/members/{member}/relationships', [\App\Http\Controllers\MemberRelationshipController::class, 'store'])->name('members.relationships.store');
    Route::delete('/members/{member}/relationships/{related}', [\App\Http\Controllers\MemberRelationshipController::class, 'destroy'])->name('members.relationships.destroy');

    // Chart of Accounts
    Route::get('/accounts', [\App\Http\Controllers\AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [\App\Http\Controllers\AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [\App\Http\Controllers\AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [\App\Http\Controllers\AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::patch('/accounts/{account}/toggle', [\App\Http\Controllers\AccountController::class, 'toggleActive'])->name('accounts.toggle');

    // Financial Reports
    Route::get('/financial-reports', [\App\Http\Controllers\FinancialReportController::class, 'hub'])->name('finance.reports-hub');
    Route::get('/trial-balance', [\App\Http\Controllers\LedgerReportController::class, 'trialBalance'])->name('finance.trial-balance');
    Route::get('/balance-sheet', [\App\Http\Controllers\LedgerReportController::class, 'balanceSheet'])->name('finance.balance-sheet');
    Route::get('/ledger', [\App\Http\Controllers\LedgerController::class, 'index'])->name('ledger.index');
    Route::get('/ledger/{account}', [\App\Http\Controllers\LedgerController::class, 'show'])->name('ledger.show');
    Route::get('/member-ledger', [\App\Http\Controllers\MemberLedgerController::class, 'index'])->name('member-ledger.index');
    Route::get('/member-ledger/{member}', [\App\Http\Controllers\MemberLedgerController::class, 'show'])->name('member-ledger.show');
    Route::get('/member-ledger/{member}/print', [\App\Http\Controllers\MemberLedgerController::class, 'print'])->name('member-ledger.print');
    Route::get('/finance-analytics', [\App\Http\Controllers\FinanceAnalyticsController::class, 'index'])->name('finance.analytics');
    Route::get('/vouchers', [\App\Http\Controllers\PaymentVoucherController::class, 'index'])->name('vouchers.index');
    Route::get('/bank', [\App\Http\Controllers\BankTransactionController::class, 'index'])->name('bank.index');
    Route::get('/bank/create', [\App\Http\Controllers\BankTransactionController::class, 'create'])->name('bank.create');
    Route::post('/bank', [\App\Http\Controllers\BankTransactionController::class, 'store'])->name('bank.store');
    Route::get('/bank/{bank}/edit', [\App\Http\Controllers\BankTransactionController::class, 'edit'])->name('bank.edit');
    Route::put('/bank/{bank}', [\App\Http\Controllers\BankTransactionController::class, 'update'])->name('bank.update');
    Route::delete('/bank/{bank}', [\App\Http\Controllers\BankTransactionController::class, 'destroy'])->name('bank.destroy');
    Route::get('/vouchers/create', [\App\Http\Controllers\PaymentVoucherController::class, 'create'])->name('vouchers.create');
    Route::post('/vouchers', [\App\Http\Controllers\PaymentVoucherController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/{voucher}', [\App\Http\Controllers\PaymentVoucherController::class, 'show'])->name('vouchers.show');
    Route::get('/vouchers/{voucher}/print', [\App\Http\Controllers\PaymentVoucherController::class, 'print'])->name('vouchers.print');
    Route::patch('/vouchers/{voucher}/approve', [\App\Http\Controllers\PaymentVoucherController::class, 'approve'])->name('vouchers.approve');
    Route::patch('/vouchers/{voucher}/pay', [\App\Http\Controllers\PaymentVoucherController::class, 'pay'])->name('vouchers.pay');
    Route::patch('/vouchers/{voucher}/reject', [\App\Http\Controllers\PaymentVoucherController::class, 'reject'])->name('vouchers.reject');
    Route::get('/journals', [\App\Http\Controllers\JournalEntryController::class, 'index'])->name('finance.journals.index');
    Route::get('/journals/create', [\App\Http\Controllers\JournalEntryController::class, 'create'])->name('finance.journals.create');
    Route::post('/journals', [\App\Http\Controllers\JournalEntryController::class, 'store'])->name('finance.journals.store');
    Route::get('/journals/{journal}', [\App\Http\Controllers\JournalEntryController::class, 'show'])->name('finance.journals.show');
    Route::delete('/journals/{journal}', [\App\Http\Controllers\JournalEntryController::class, 'destroy'])->name('finance.journals.destroy');
    Route::get('/finance-statement', [\App\Http\Controllers\FinancialReportController::class, 'statement'])->name('finance.statement');
    Route::get('/finance-statement/pdf', [\App\Http\Controllers\FinancialReportController::class, 'statementPdf'])->name('finance.statement.pdf');
    Route::get('/income-note', [\App\Http\Controllers\FinancialReportController::class, 'incomeNote'])->name('finance.income-note');
    Route::get('/income-note/pdf', [\App\Http\Controllers\FinancialReportController::class, 'incomeNotePdf'])->name('finance.income-note.pdf');
    Route::get('/expenditure-note', [\App\Http\Controllers\FinancialReportController::class, 'expenditureNote'])->name('finance.expenditure-note');
    Route::get('/expenditure-note/pdf', [\App\Http\Controllers\FinancialReportController::class, 'expenditureNotePdf'])->name('finance.expenditure-note.pdf');
    Route::get('/master-report/pdf', [\App\Http\Controllers\FinancialReportController::class, 'masterReportPdf'])->name('finance.master-report.pdf');
    // Budget vs Actual
    Route::get('/budget', [\App\Http\Controllers\BudgetController::class, 'report'])->name('finance.budget.report');

    // Harvest campaigns
    Route::get('/harvests', [\App\Http\Controllers\HarvestController::class, 'index'])->name('harvests.index');
    Route::get('/harvests/create', [\App\Http\Controllers\HarvestController::class, 'create'])->name('harvests.create');
    Route::post('/harvests', [\App\Http\Controllers\HarvestController::class, 'store'])->name('harvests.store');
    Route::get('/harvests/{harvest}/edit', [\App\Http\Controllers\HarvestController::class, 'edit'])->name('harvests.edit');
    Route::put('/harvests/{harvest}', [\App\Http\Controllers\HarvestController::class, 'update'])->name('harvests.update');
    Route::get('/harvests/{harvest}', [\App\Http\Controllers\HarvestController::class, 'show'])->name('harvests.show');
    Route::delete('/harvests/{harvest}', [\App\Http\Controllers\HarvestController::class, 'destroy'])->name('harvests.destroy');
    Route::get('/budget/edit', [\App\Http\Controllers\BudgetController::class, 'edit'])->name('finance.budget.edit');
    Route::post('/budget', [\App\Http\Controllers\BudgetController::class, 'update'])->name('finance.budget.update');
});

require __DIR__.'/auth.php';