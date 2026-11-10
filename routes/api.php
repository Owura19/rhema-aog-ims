<?php

use App\Http\Controllers\Api\V1\Auth\ActivationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\DevotionController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Member\CalendarController;
use App\Http\Controllers\Api\V1\Member\EventController;
use App\Http\Controllers\Api\V1\Member\EventRsvpController;
use App\Http\Controllers\Api\V1\Member\GivingController;
use App\Http\Controllers\Api\V1\Member\PledgeController;
use App\Http\Controllers\Api\V1\Member\ProfileController;
use App\Http\Controllers\Api\V1\Member\UpcomingProgramsController;
use App\Http\Controllers\Api\V1\MePasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — version 1  (/api/v1/...)
|--------------------------------------------------------------------------
| The 'api' group (bootstrap/app.php) already applies: JSON-only responses,
| the 'api' rate limiter, and route-model binding. Errors use one shape:
| { message, code, errors? } — see App\Support\Api\ApiExceptionRenderer.
|
| Authenticated routes: auth:sanctum + active. Member-only routes add 'member'.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::get('/health', HealthController::class)->name('health');

    // ── Member self-activation and phone login ──────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        // Phone-based: 5/min per normalised phone, 100/min per IP (plus SMS caps in the controller).
        Route::middleware('throttle:api-auth')->group(function () {
            Route::post('/activation/request', [ActivationController::class, 'requestCode'])->name('activation.request');
            Route::post('/activation/verify', [ActivationController::class, 'verifyCode'])->name('activation.verify');
            Route::post('/login', LoginController::class)->name('login');
            Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
            Route::post('/password/reset', [PasswordResetController::class, 'reset'])->name('password.reset');
        });

        // No phone here; a 64-char single-use session token. The general 'api' limit applies.
        Route::post('/activation/complete', [ActivationController::class, 'complete'])->name('activation.complete');

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
            Route::post('/logout-all', [LogoutController::class, 'logoutAll'])->name('logout-all');
        });
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/me', MeController::class)->name('me');
        Route::put('/me/password', MePasswordController::class)
            ->middleware('throttle:password-change')
            ->name('me.password');

        // Daily devotions: any active login (staff without a member record too), live devotions only.
        Route::get('/devotions/today', [DevotionController::class, 'today'])->name('devotions.today');
        Route::get('/devotions', [DevotionController::class, 'index'])->name('devotions.index');
        Route::get('/devotions/{date}', [DevotionController::class, 'show'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('devotions.show');
    });

    // ── Member features: the signed-in member's own data only ───────────
    // The member is always $request->user()->member; no route takes a member id.
    Route::middleware(['auth:sanctum', 'active', 'member'])->group(function () {
        Route::get('/me/profile', ProfileController::class)->name('me.profile');
        Route::get('/me/giving', GivingController::class)->name('me.giving');
        Route::get('/me/pledges', PledgeController::class)->name('me.pledges');

        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/events/{event}', [EventController::class, 'show'])->where('event', '[0-9]{1,18}')->name('events.show');
        Route::post('/events/{event}/rsvp', [EventRsvpController::class, 'store'])->where('event', '[0-9]{1,18}')->name('events.rsvp.store');
        Route::delete('/events/{event}/rsvp', [EventRsvpController::class, 'destroy'])->where('event', '[0-9]{1,18}')->name('events.rsvp.destroy');

        // Church calendar: events + services in one date-sorted feed
        Route::get('/calendar', CalendarController::class)->name('calendar');
        Route::get('/programs/upcoming', UpcomingProgramsController::class)->name('programs.upcoming');
    });
});
