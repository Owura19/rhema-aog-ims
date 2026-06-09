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

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {

    // Dashboard — any logged-in user
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
        Route::patch('/members/{member}', [MemberController::class, 'update']);
    });
    Route::middleware('permission:delete members')->group(function () {
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    });
    Route::middleware('permission:view members')->group(function () {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
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
        Route::get('/finance/{finance}', [FinanceController::class, 'show'])->name('finance.show');
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

});

require __DIR__.'/auth.php';