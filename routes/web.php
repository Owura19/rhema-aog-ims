<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ChurchServiceController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BiometricDeviceController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\CellGroupController;
use App\Http\Controllers\DevotionController;
use App\Http\Controllers\DevotionThemeController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CommunityModerationController;
use App\Http\Controllers\DayBornOfferingController;
use App\Http\Controllers\RandyImpactAIController;
use App\Http\Controllers\SermonController;
use App\Http\Controllers\SermonSeriesController;
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
        // Mobile app login given by staff (phone + temporary password)
        Route::post('/members/{member}/app-login', [\App\Http\Controllers\MemberAppLoginController::class, 'store'])->name('members.app-login.store');
        Route::post('/members/{member}/app-login/reset', [\App\Http\Controllers\MemberAppLoginController::class, 'resetPassword'])->name('members.app-login.reset');
        Route::delete('/members/{member}/app-login', [\App\Http\Controllers\MemberAppLoginController::class, 'destroy'])->name('members.app-login.destroy');
        Route::patch('/members/{member}', [MemberController::class, 'update']);

        // Member family relationships (buttons in members/show are gated by 'edit members')
        Route::post('/members/{member}/relationships', [\App\Http\Controllers\MemberRelationshipController::class, 'store'])->name('members.relationships.store');
        Route::delete('/members/{member}/relationships/{related}', [\App\Http\Controllers\MemberRelationshipController::class, 'destroy'])->name('members.relationships.destroy');
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
        Route::get('/tithe-report', [FinanceController::class, 'titheReport'])->name('finance.tithe-report');
        Route::get('/finance-export', [FinanceController::class, 'exportExcel'])->name('finance.export');
        Route::get('/finance-export-pdf', [FinanceController::class, 'exportPdf'])->name('finance.pdf');
        Route::get('/finance/{finance}', [FinanceController::class, 'show'])->name('finance.show');
    });
    Route::middleware('permission:view finance|manage pledges')->group(function () {
        Route::get('/receipts/{transaction}', [FinanceController::class, 'receiptView'])->name('finance.receipt');
        Route::get('/receipts/{transaction}/download', [FinanceController::class, 'receipt'])->name('finance.receipt.download');
        Route::get('/receipts/{transaction}/print', [FinanceController::class, 'print'])->name('finance.receipt.print');
    });
    Route::middleware('permission:view finance')->group(function () {
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
        // RSVP — staff with 'view events' can add or cancel RSVPs
        Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('events.rsvp');
        Route::delete('/events/{event}/rsvp/{rsvp}', [EventController::class, 'cancelRsvp'])->name('events.cancel-rsvp');
    });

    // ─────────────────────────────────────────────────────────────
    // DEVOTIONS — view: 'view devotions' | write + themes: 'manage devotions'
    // | approve / withdraw: 'approve devotions'. Deleting an APPROVED devotion
    // also needs 'approve devotions' (checked in the controller).
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:manage devotions')->group(function () {
        Route::get('/devotions/create', [DevotionController::class, 'create'])->name('devotions.create');
        Route::post('/devotions', [DevotionController::class, 'store'])->name('devotions.store');
        Route::get('/devotions/{devotion}/edit', [DevotionController::class, 'edit'])->whereNumber('devotion')->name('devotions.edit');
        Route::put('/devotions/{devotion}', [DevotionController::class, 'update'])->whereNumber('devotion')->name('devotions.update');
        Route::delete('/devotions/{devotion}', [DevotionController::class, 'destroy'])->whereNumber('devotion')->name('devotions.destroy');

        Route::get('/devotion-themes', [DevotionThemeController::class, 'index'])->name('devotion-themes.index');
        Route::get('/devotion-themes/create', [DevotionThemeController::class, 'create'])->name('devotion-themes.create');
        Route::post('/devotion-themes', [DevotionThemeController::class, 'store'])->name('devotion-themes.store');
        Route::get('/devotion-themes/{theme}/edit', [DevotionThemeController::class, 'edit'])->whereNumber('theme')->name('devotion-themes.edit');
        Route::put('/devotion-themes/{theme}', [DevotionThemeController::class, 'update'])->whereNumber('theme')->name('devotion-themes.update');
        Route::delete('/devotion-themes/{theme}', [DevotionThemeController::class, 'destroy'])->whereNumber('theme')->name('devotion-themes.destroy');
    });
    Route::middleware('permission:approve devotions')->group(function () {
        Route::post('/devotions/{devotion}/approve', [DevotionController::class, 'approve'])->whereNumber('devotion')->name('devotions.approve');
        Route::post('/devotions/{devotion}/withdraw', [DevotionController::class, 'withdraw'])->whereNumber('devotion')->name('devotions.withdraw');
    });
    Route::middleware('permission:view devotions')->group(function () {
        Route::get('/devotions', [DevotionController::class, 'index'])->name('devotions.index');
        Route::get('/devotions/{devotion}', [DevotionController::class, 'show'])->whereNumber('devotion')->name('devotions.show');
    });

    // ─────────────────────────────────────────────────────────────
    // SERMONS — view: 'view sermons' | write, publish, delete, series: 'manage sermons'
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:manage sermons')->group(function () {
        Route::get('/sermons/create', [SermonController::class, 'create'])->name('sermons.create');
        Route::post('/sermons', [SermonController::class, 'store'])->name('sermons.store');
        Route::get('/sermons/{sermon}/edit', [SermonController::class, 'edit'])->whereNumber('sermon')->name('sermons.edit');
        Route::put('/sermons/{sermon}', [SermonController::class, 'update'])->whereNumber('sermon')->name('sermons.update');
        Route::delete('/sermons/{sermon}', [SermonController::class, 'destroy'])->whereNumber('sermon')->name('sermons.destroy');
        Route::post('/sermons/{sermon}/publish', [SermonController::class, 'publish'])->whereNumber('sermon')->name('sermons.publish');
        Route::post('/sermons/{sermon}/unpublish', [SermonController::class, 'unpublish'])->whereNumber('sermon')->name('sermons.unpublish');

        Route::get('/sermon-series', [SermonSeriesController::class, 'index'])->name('sermon-series.index');
        Route::get('/sermon-series/create', [SermonSeriesController::class, 'create'])->name('sermon-series.create');
        Route::post('/sermon-series', [SermonSeriesController::class, 'store'])->name('sermon-series.store');
        Route::get('/sermon-series/{series}/edit', [SermonSeriesController::class, 'edit'])->whereNumber('series')->name('sermon-series.edit');
        Route::put('/sermon-series/{series}', [SermonSeriesController::class, 'update'])->whereNumber('series')->name('sermon-series.update');
        Route::delete('/sermon-series/{series}', [SermonSeriesController::class, 'destroy'])->whereNumber('series')->name('sermon-series.destroy');
    });
    Route::middleware('permission:view sermons')->group(function () {
        Route::get('/sermons', [SermonController::class, 'index'])->name('sermons.index');
        Route::get('/sermons/{sermon}', [SermonController::class, 'show'])->whereNumber('sermon')->name('sermons.show');
    });

    // DAY-BORN OFFERING — record: ushers, office staff, finance | publish: Finance Officer, Super Admin | view: + Pastor
    // {week} is the service date (2026-11-15). Editing a published week also needs 'publish day-born offering'.
    Route::middleware('permission:record day-born offering')->group(function () {
        Route::get('/day-born/entry', [DayBornOfferingController::class, 'create'])->name('day-born.create');
        Route::post('/day-born', [DayBornOfferingController::class, 'store'])->name('day-born.store');
        Route::get('/day-born/{week}/edit', [DayBornOfferingController::class, 'edit'])->where('week', '\d{4}-\d{2}-\d{2}')->name('day-born.edit');
        Route::put('/day-born/{week}', [DayBornOfferingController::class, 'update'])->where('week', '\d{4}-\d{2}-\d{2}')->name('day-born.update');
        Route::delete('/day-born/{week}', [DayBornOfferingController::class, 'destroy'])->where('week', '\d{4}-\d{2}-\d{2}')->name('day-born.destroy');
    });
    Route::middleware('permission:publish day-born offering')->group(function () {
        Route::post('/day-born/{week}/publish', [DayBornOfferingController::class, 'publish'])->where('week', '\d{4}-\d{2}-\d{2}')->name('day-born.publish');
        Route::post('/day-born/{week}/unpublish', [DayBornOfferingController::class, 'unpublish'])->where('week', '\d{4}-\d{2}-\d{2}')->name('day-born.unpublish');
    });
    Route::middleware('permission:view day-born offering')->group(function () {
        Route::get('/day-born', [DayBornOfferingController::class, 'index'])->name('day-born.index');
        Route::get('/day-born/{week}', [DayBornOfferingController::class, 'show'])->where('week', '\d{4}-\d{2}-\d{2}')->name('day-born.show');
    });

    // ANNOUNCEMENTS (the app's Home) — 'manage announcements': Super Admin, Pastor, Office Staff
    Route::middleware('permission:manage announcements')->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->whereNumber('announcement')->name('announcements.edit');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->whereNumber('announcement')->name('announcements.update');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->whereNumber('announcement')->name('announcements.destroy');
        Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->whereNumber('announcement')->name('announcements.publish');
        Route::post('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])->whereNumber('announcement')->name('announcements.unpublish');
    });

    // COMMUNITY MODERATION (the members' app feed) — 'moderate community': Super Admin + Pastor
    Route::middleware('permission:moderate community')->prefix('community-moderation')->name('community-moderation.')->group(function () {
        Route::get('/', [CommunityModerationController::class, 'index'])->name('index');
        Route::get('/members/{member}', [CommunityModerationController::class, 'member'])->whereNumber('member')->name('member');
        Route::post('/members/{member}/ban', [CommunityModerationController::class, 'ban'])->whereNumber('member')->name('ban');
        Route::post('/bans/{ban}/lift', [CommunityModerationController::class, 'liftBan'])->whereNumber('ban')->name('lift-ban');
        Route::get('/photos/{photo}/{variant}', [CommunityModerationController::class, 'photo'])
            ->where(['photo' => '[0-9]+', 'variant' => 'full|thumb'])->name('photo');
        Route::get('/{type}/{id}', [CommunityModerationController::class, 'show'])->where(['type' => 'post|comment', 'id' => '[0-9]+'])->name('show');
        Route::post('/{type}/{id}/hide', [CommunityModerationController::class, 'hide'])->where(['type' => 'post|comment', 'id' => '[0-9]+'])->name('hide');
        Route::post('/{type}/{id}/unhide', [CommunityModerationController::class, 'unhide'])->where(['type' => 'post|comment', 'id' => '[0-9]+'])->name('unhide');
        Route::post('/{type}/{id}/dismiss', [CommunityModerationController::class, 'dismiss'])->where(['type' => 'post|comment', 'id' => '[0-9]+'])->name('dismiss');
    });

    // ─────────────────────────────────────────────────────────────
    // VISITORS — view/create/edit/delete: matching 'visitors' permission
    // Convert to member: 'create members' (it creates a member record).
    // Follow-up tracking with one-click conversion to member.
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:create visitors')->group(function () {
        Route::get('/visitors/create', [VisitorController::class, 'create'])->name('visitors.create');
        Route::post('/visitors', [VisitorController::class, 'store'])->name('visitors.store');
    });
    Route::middleware('permission:edit visitors')->group(function () {
        Route::get('/visitors/{visitor}/edit', [VisitorController::class, 'edit'])->name('visitors.edit');
        Route::match(['put', 'patch'], '/visitors/{visitor}', [VisitorController::class, 'update'])->name('visitors.update');
    });
    Route::middleware('permission:delete visitors')->group(function () {
        Route::delete('/visitors/{visitor}', [VisitorController::class, 'destroy'])->name('visitors.destroy');
    });
    Route::middleware('permission:create members')->group(function () {
        Route::post('/visitors/{visitor}/convert', [VisitorController::class, 'convertToMember'])->name('visitors.convert');
    });
    Route::middleware('permission:view visitors')->group(function () {
        Route::get('/visitors', [VisitorController::class, 'index'])->name('visitors.index');
        Route::get('/visitors/{visitor}', [VisitorController::class, 'show'])->name('visitors.show');
    });

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

    // The old staff "Community" page (CommunityController, /community) is retired: the
    // members' feed lives in the app (api/v1/community, moderated at /community-moderation).
    // Its tables (posts, post_likes, post_comments) and models are kept untouched.

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
    Route::middleware('permission:view pledges')->group(function () {
        Route::get('/pledges', [\App\Http\Controllers\PledgeController::class, 'index'])->name('pledges.index');
        Route::get('/pledges/report', [\App\Http\Controllers\PledgeController::class, 'report'])->name('pledges.report');
        Route::get('/pledges/report/pdf', [\App\Http\Controllers\PledgeController::class, 'reportPdf'])->name('pledges.report.pdf');
        Route::get('/pledges/{pledge}', [\App\Http\Controllers\PledgeController::class, 'show'])->whereNumber('pledge')->name('pledges.show');
        Route::get('/payments', [\App\Http\Controllers\PledgeController::class, 'paymentsIndex'])->name('payments.index');
        Route::get('/pledges-summary', [\App\Http\Controllers\PledgeController::class, 'summaryByService'])->name('pledges.summary');
    });
    Route::middleware('permission:edit pledges')->group(function () {
        Route::get('/pledges/{pledge}/edit', [\App\Http\Controllers\PledgeController::class, 'edit'])->whereNumber('pledge')->name('pledges.edit');
        Route::patch('/pledges/{pledge}', [\App\Http\Controllers\PledgeController::class, 'update'])->whereNumber('pledge')->name('pledges.update');
        Route::get('/pledges/{pledge}/payments/{payment}/edit', [\App\Http\Controllers\PledgeController::class, 'editPayment'])->whereNumber('pledge')->whereNumber('payment')->name('pledges.payments.edit');
        Route::patch('/pledges/{pledge}/payments/{payment}', [\App\Http\Controllers\PledgeController::class, 'updatePayment'])->whereNumber('pledge')->whereNumber('payment')->name('pledges.payments.update');
        Route::delete('/pledges/{pledge}/payments/{payment}', [\App\Http\Controllers\PledgeController::class, 'destroyPayment'])->whereNumber('pledge')->whereNumber('payment')->name('pledges.payments.destroy');
    });
    Route::middleware('permission:manage pledges')->group(function () {
        Route::get('/pledges/create', [\App\Http\Controllers\PledgeController::class, 'create'])->name('pledges.create');
        Route::post('/pledges', [\App\Http\Controllers\PledgeController::class, 'store'])->name('pledges.store');
        Route::post('/pledges/{pledge}/payments', [\App\Http\Controllers\PledgeController::class, 'storePayment'])->name('pledges.payments.store');
        Route::patch('/pledges/{pledge}/cancel', [\App\Http\Controllers\PledgeController::class, 'cancel'])->name('pledges.cancel');
    });
    Route::middleware('permission:delete finance')->group(function () {
        Route::delete('/pledges/{pledge}', [\App\Http\Controllers\PledgeController::class, 'destroy'])->name('pledges.destroy');
    });

    // Chart of Accounts
    Route::middleware('permission:view finance')->group(function () {
        Route::get('/accounts', [\App\Http\Controllers\AccountController::class, 'index'])->name('accounts.index');
    });
    Route::middleware('permission:create finance')->group(function () {
        Route::post('/accounts', [\App\Http\Controllers\AccountController::class, 'store'])->name('accounts.store');
    });
    Route::middleware('permission:edit finance')->group(function () {
        Route::put('/accounts/{account}', [\App\Http\Controllers\AccountController::class, 'update'])->name('accounts.update');
    });
    Route::middleware('permission:delete finance')->group(function () {
        Route::delete('/accounts/{account}', [\App\Http\Controllers\AccountController::class, 'destroy'])->name('accounts.destroy');
    });
    Route::middleware('permission:edit finance')->group(function () {
        Route::patch('/accounts/{account}/toggle', [\App\Http\Controllers\AccountController::class, 'toggleActive'])->name('accounts.toggle');
    });

    // Financial Reports
    Route::middleware('permission:view finance')->group(function () {
        Route::get('/financial-reports', [\App\Http\Controllers\FinancialReportController::class, 'hub'])->name('finance.reports-hub');
        Route::get('/trial-balance', [\App\Http\Controllers\LedgerReportController::class, 'trialBalance'])->name('finance.trial-balance');
        Route::get('/balance-sheet', [\App\Http\Controllers\LedgerReportController::class, 'balanceSheet'])->name('finance.balance-sheet');
        Route::get('/ledger', [\App\Http\Controllers\LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/ledger/{account}', [\App\Http\Controllers\LedgerController::class, 'show'])->name('ledger.show');
        Route::get('/ledger/{account}/pdf', [\App\Http\Controllers\LedgerController::class, 'exportPdf'])->name('ledger.pdf');
        Route::get('/member-ledger', [\App\Http\Controllers\MemberLedgerController::class, 'index'])->name('member-ledger.index');
        Route::get('/member-ledger/{member}', [\App\Http\Controllers\MemberLedgerController::class, 'show'])->name('member-ledger.show');
        Route::get('/member-ledger/{member}/print', [\App\Http\Controllers\MemberLedgerController::class, 'print'])->name('member-ledger.print');
        Route::get('/finance-analytics', [\App\Http\Controllers\FinanceAnalyticsController::class, 'index'])->name('finance.analytics');
        Route::get('/vouchers', [\App\Http\Controllers\PaymentVoucherController::class, 'index'])->name('vouchers.index');
        Route::get('/bank', [\App\Http\Controllers\BankTransactionController::class, 'index'])->name('bank.index');
    });
    Route::middleware('permission:create finance')->group(function () {
        Route::get('/bank/create', [\App\Http\Controllers\BankTransactionController::class, 'create'])->name('bank.create');
        Route::post('/bank', [\App\Http\Controllers\BankTransactionController::class, 'store'])->name('bank.store');
    });
    Route::middleware('permission:edit finance')->group(function () {
        Route::get('/bank/{bank}/edit', [\App\Http\Controllers\BankTransactionController::class, 'edit'])->name('bank.edit');
        Route::put('/bank/{bank}', [\App\Http\Controllers\BankTransactionController::class, 'update'])->name('bank.update');
    });
    Route::middleware('permission:delete finance')->group(function () {
        Route::delete('/bank/{bank}', [\App\Http\Controllers\BankTransactionController::class, 'destroy'])->name('bank.destroy');
    });
    Route::middleware('permission:create finance')->group(function () {
        Route::get('/vouchers/create', [\App\Http\Controllers\PaymentVoucherController::class, 'create'])->name('vouchers.create');
        Route::post('/vouchers', [\App\Http\Controllers\PaymentVoucherController::class, 'store'])->name('vouchers.store');
    });
    Route::middleware('permission:view finance')->group(function () {
        Route::get('/vouchers/{voucher}', [\App\Http\Controllers\PaymentVoucherController::class, 'show'])->name('vouchers.show');
        Route::get('/vouchers/{voucher}/print', [\App\Http\Controllers\PaymentVoucherController::class, 'print'])->name('vouchers.print');
    });
    Route::middleware('permission:edit finance')->group(function () {
        Route::patch('/vouchers/{voucher}/approve', [\App\Http\Controllers\PaymentVoucherController::class, 'approve'])->name('vouchers.approve');
        Route::patch('/vouchers/{voucher}/pay', [\App\Http\Controllers\PaymentVoucherController::class, 'pay'])->name('vouchers.pay');
        Route::patch('/vouchers/{voucher}/reject', [\App\Http\Controllers\PaymentVoucherController::class, 'reject'])->name('vouchers.reject');
        Route::middleware('permission:edit vouchers')->group(function () {
            Route::get('/vouchers/{voucher}/edit', [\App\Http\Controllers\PaymentVoucherController::class, 'edit'])->name('vouchers.edit');
            Route::patch('/vouchers/{voucher}', [\App\Http\Controllers\PaymentVoucherController::class, 'update'])->name('vouchers.update');
            Route::delete('/vouchers/{voucher}', [\App\Http\Controllers\PaymentVoucherController::class, 'destroy'])->name('vouchers.destroy');
        });
    });
    Route::middleware('role:Super Admin')->group(function () {
        Route::get('/journals', [\App\Http\Controllers\JournalEntryController::class, 'index'])->name('finance.journals.index');
        Route::get('/journals/create', [\App\Http\Controllers\JournalEntryController::class, 'create'])->name('finance.journals.create');
        Route::post('/journals', [\App\Http\Controllers\JournalEntryController::class, 'store'])->name('finance.journals.store');
        Route::get('/journals/{journal}', [\App\Http\Controllers\JournalEntryController::class, 'show'])->name('finance.journals.show');
        Route::delete('/journals/{journal}', [\App\Http\Controllers\JournalEntryController::class, 'destroy'])->name('finance.journals.destroy');
    });
    Route::middleware('permission:view finance')->group(function () {
        Route::get('/finance-statement', [\App\Http\Controllers\FinancialReportController::class, 'statement'])->name('finance.statement');
        Route::get('/finance-statement/pdf', [\App\Http\Controllers\FinancialReportController::class, 'statementPdf'])->name('finance.statement.pdf');
        Route::get('/cash-summary', [\App\Http\Controllers\FinancialReportController::class, 'cashSummary'])->name('finance.cash-summary');
        Route::get('/income-note', [\App\Http\Controllers\FinancialReportController::class, 'incomeNote'])->name('finance.income-note');
        Route::get('/income-note/pdf', [\App\Http\Controllers\FinancialReportController::class, 'incomeNotePdf'])->name('finance.income-note.pdf');
        Route::get('/expenditure-note', [\App\Http\Controllers\FinancialReportController::class, 'expenditureNote'])->name('finance.expenditure-note');
        Route::get('/expenditure-note/pdf', [\App\Http\Controllers\FinancialReportController::class, 'expenditureNotePdf'])->name('finance.expenditure-note.pdf');
        Route::get('/master-report/pdf', [\App\Http\Controllers\FinancialReportController::class, 'masterReportPdf'])->name('finance.master-report.pdf');
    });
    // ─────────────────────────────────────────────────────────────
    // BUDGET — report: 'view finance' | set/edit figures: 'edit finance'
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:view finance')->group(function () {
        Route::get('/budget', [\App\Http\Controllers\BudgetController::class, 'report'])->name('finance.budget.report');
    });
    Route::middleware('permission:edit finance')->group(function () {
        Route::get('/budget/edit', [\App\Http\Controllers\BudgetController::class, 'edit'])->name('finance.budget.edit');
        Route::post('/budget', [\App\Http\Controllers\BudgetController::class, 'update'])->name('finance.budget.update');
    });

    // ─────────────────────────────────────────────────────────────
    // HARVEST CAMPAIGNS — view: 'view finance' OR 'view pledges'
    // create: 'create finance' | edit: 'edit finance' | delete: 'delete finance'
    // ─────────────────────────────────────────────────────────────
    Route::middleware('permission:create finance')->group(function () {
        Route::get('/harvests/create', [\App\Http\Controllers\HarvestController::class, 'create'])->name('harvests.create');
        Route::post('/harvests', [\App\Http\Controllers\HarvestController::class, 'store'])->name('harvests.store');
    });
    Route::middleware('permission:edit finance')->group(function () {
        Route::get('/harvests/{harvest}/edit', [\App\Http\Controllers\HarvestController::class, 'edit'])->name('harvests.edit');
        Route::match(['put', 'patch'], '/harvests/{harvest}', [\App\Http\Controllers\HarvestController::class, 'update'])->name('harvests.update');
    });
    Route::middleware('permission:delete finance')->group(function () {
        Route::delete('/harvests/{harvest}', [\App\Http\Controllers\HarvestController::class, 'destroy'])->name('harvests.destroy');
    });
    Route::middleware('permission:view finance|view pledges')->group(function () {
        Route::get('/harvests', [\App\Http\Controllers\HarvestController::class, 'index'])->name('harvests.index');
        Route::get('/harvests/{harvest}', [\App\Http\Controllers\HarvestController::class, 'show'])->name('harvests.show');
    });
});

require __DIR__.'/auth.php';