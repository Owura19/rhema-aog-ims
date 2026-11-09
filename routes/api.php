<?php

use App\Http\Controllers\Api\V1\Auth\ActivationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
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
    });
});
