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

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Members
    Route::resource('members', MemberController::class);

    // Church Services
    Route::resource('services', ChurchServiceController::class);

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');
    Route::post('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk-mark');
    Route::post('/attendance/sync-biometric', [AttendanceController::class, 'syncBiometric'])->name('attendance.sync-biometric');
    Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');
    Route::delete('/attendance/{attendanceLog}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

    // Biometric Devices
    Route::resource('devices', BiometricDeviceController::class);
    Route::post('/devices/{device}/test', [BiometricDeviceController::class, 'testConnection'])->name('devices.test');

    // Finance
    Route::resource('finance', FinanceController::class);
    Route::get('/finance-report', [FinanceController::class, 'report'])->name('finance.report');

    // Cell Groups & Departments
    Route::resource('cellgroups', CellGroupController::class);
    Route::post('/cellgroups/{cellgroup}/members', [CellGroupController::class, 'addMember'])->name('cellgroups.add-member');
    Route::delete('/cellgroups/{cellgroup}/members/{member}', [CellGroupController::class, 'removeMember'])->name('cellgroups.remove-member');

    // Events & Programs
    Route::resource('events', EventController::class);
    Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('events.rsvp');
    Route::delete('/events/{event}/rsvp/{rsvp}', [EventController::class, 'cancelRsvp'])->name('events.cancel-rsvp');

    // Community
    Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
    Route::post('/community', [CommunityController::class, 'store'])->name('community.store');
    Route::delete('/community/{post}', [CommunityController::class, 'destroy'])->name('community.destroy');
    Route::post('/community/{post}/like', [CommunityController::class, 'like'])->name('community.like');
    Route::post('/community/{post}/comment', [CommunityController::class, 'comment'])->name('community.comment');
    Route::delete('/community/comments/{comment}', [CommunityController::class, 'deleteComment'])->name('community.delete-comment');
    Route::post('/community/{post}/pin', [CommunityController::class, 'pin'])->name('community.pin');

    // RandyImpact AI
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