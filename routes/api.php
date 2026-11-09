<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
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

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/me', MeController::class)->name('me');
    });
});
