<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| JSON API のルート定義。
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationController::class, 'apiIndex'])
        ->name('api.notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'apiMarkAllAsRead'])
        ->name('api.notifications.markAllAsRead');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'apiMarkAsRead'])
        ->name('api.notifications.markAsRead');
});
