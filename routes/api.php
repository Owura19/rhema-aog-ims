<?php

use App\Http\Controllers\Api\V1\Auth\ActivationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\BibleController;
use App\Http\Controllers\Api\V1\DayBornController;
use App\Http\Controllers\Api\V1\Community\BlockController as CommunityBlockController;
use App\Http\Controllers\Api\V1\Community\CommentController as CommunityCommentController;
use App\Http\Controllers\Api\V1\Community\ReportController as CommunityReportController;
use App\Http\Controllers\Api\V1\Community\LikeController as CommunityLikeController;
use App\Http\Controllers\Api\V1\Community\PhotoController as CommunityPhotoController;
use App\Http\Controllers\Api\V1\Community\PostController as CommunityPostController;
use App\Http\Controllers\Api\V1\Community\RulesController as CommunityRulesController;
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
use App\Http\Controllers\Api\V1\SermonController;
use App\Http\Controllers\Api\V1\SermonSeriesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — version 1  (/api/v1/...)
|--------------------------------------------------------------------------
| The 'api' group (bootstrap/app.php) already applies: JSON-only responses,
| the 'api' rate limiter, and route-model binding. Errors use one shape:
| { message, code, errors? } — see App\Support\Api\ApiExceptionRenderer.
|
| Authenticated routes: auth:sanctum + active + password.changed (a temporary
| password allows only /me, /me/password and logout). Member-only routes add 'member'.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::get('/health', HealthController::class)->name('health');

    // Community photos: signed URLs only (no token, so image components can load them).
    // Own limiter per viewer instead of the shared per-IP one: a feed page loads many photos.
    Route::get('/community/photos/{photo}/{variant}', [CommunityPhotoController::class, 'show'])
        ->where(['photo' => '[0-9]{1,18}', 'variant' => 'full|thumb'])
        ->withoutMiddleware('throttle:api')
        ->middleware(['signed:relative', 'throttle:community-photos'])
        ->name('community.photos.show');

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

        Route::middleware(['auth:sanctum', 'active', 'password.changed'])->group(function () {
            Route::post('/logout',[LogoutController::class, 'logout'])->name('logout');
            Route::post('/logout-all', [LogoutController::class, 'logoutAll'])->name('logout-all');
        });
    });

    Route::middleware(['auth:sanctum', 'active', 'password.changed'])->group(function () {
        Route::get('/me',MeController::class)->name('me');
        Route::put('/me/password', MePasswordController::class)
            ->middleware('throttle:password-change')
            ->name('me.password');

        // Daily devotions: any active login (staff without a member record too), live devotions only.
        Route::get('/devotions/today', [DevotionController::class, 'today'])->name('devotions.today');
        Route::get('/devotions', [DevotionController::class, 'index'])->name('devotions.index');
        Route::get('/devotions/{date}', [DevotionController::class, 'show'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('devotions.show');

        // Sermons: any active login, published sermons only.
        Route::get('/sermons', [SermonController::class, 'index'])->name('sermons.index');
        Route::get('/sermons/{sermon}', [SermonController::class, 'show'])->where('sermon', '[0-9]{1,18}')->name('sermons.show');
        Route::get('/sermon-series', SermonSeriesController::class)->name('sermon-series.index');

        // Announcements for Home: any active login, live ones only.
        Route::get('/announcements', AnnouncementController::class)->name('announcements.index');

        // Day-born offering results: any active login, published weeks only (group totals, never per member).
        Route::get('/day-born/latest', [DayBornController::class, 'latest'])->name('day-born.latest');
        Route::get('/day-born', [DayBornController::class, 'index'])->name('day-born.index');

        // KJV reader: any active login.
        Route::get('/bible/books', [BibleController::class, 'books'])->name('bible.books');
        Route::get('/bible/{book}/{chapter}', [BibleController::class, 'chapter'])
            ->where(['book' => '[0-9]{1,3}', 'chapter' => '[0-9]{1,3}'])->name('bible.chapter');
    });

    // ── Member features: the signed-in member's own data only ───────────
    // The member is always $request->user()->member; no route takes a member id.
    Route::middleware(['auth:sanctum', 'active', 'password.changed', 'member'])->group(function () {
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

        // Community feed (config/community.php). Reading is open to every member; posting,
        // commenting and liking need CommunityAccess (rules accepted, not banned, 18+).
        Route::prefix('community')->name('community.')->group(function () {
            Route::get('/rules', [CommunityRulesController::class, 'show'])->name('rules.show');
            Route::post('/rules/accept', [CommunityRulesController::class, 'accept'])->name('rules.accept');

            Route::get('/posts', [CommunityPostController::class, 'index'])->name('posts.index');
            Route::post('/posts', [CommunityPostController::class, 'store'])->middleware('throttle:community-posts')->name('posts.store');
            Route::get('/posts/{post}', [CommunityPostController::class, 'show'])->where('post', '[0-9]{1,18}')->name('posts.show');
            Route::delete('/posts/{post}', [CommunityPostController::class, 'destroy'])->where('post', '[0-9]{1,18}')->name('posts.destroy');

            Route::post('/posts/{post}/like', [CommunityLikeController::class, 'store'])->where('post', '[0-9]{1,18}')->middleware('throttle:community-likes')->name('posts.like');
            Route::delete('/posts/{post}/like', [CommunityLikeController::class, 'destroy'])->where('post', '[0-9]{1,18}')->middleware('throttle:community-likes')->name('posts.unlike');

            Route::get('/posts/{post}/comments', [CommunityCommentController::class, 'index'])->where('post', '[0-9]{1,18}')->name('comments.index');
            Route::post('/posts/{post}/comments', [CommunityCommentController::class, 'store'])->where('post', '[0-9]{1,18}')->middleware('throttle:community-comments')->name('comments.store');
            Route::delete('/comments/{comment}', [CommunityCommentController::class, 'destroy'])->where('comment', '[0-9]{1,18}')->name('comments.destroy');

            // Safety tools: open to every member (minors and banned members too).
            Route::post('/reports', [CommunityReportController::class, 'store'])->middleware('throttle:community-reports')->name('reports.store');
            Route::get('/blocks', [CommunityBlockController::class, 'index'])->name('blocks.index');
            Route::post('/blocks/{member}', [CommunityBlockController::class, 'store'])->where('member', '[0-9]{1,18}')->name('blocks.store');
            Route::delete('/blocks/{member}', [CommunityBlockController::class, 'destroy'])->where('member', '[0-9]{1,18}')->name('blocks.destroy');
        });
    });
});
