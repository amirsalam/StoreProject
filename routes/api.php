<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Public API. Guarded by Sanctum personal access tokens issued from
 * /settings/api-tokens. Each request must carry the token as a bearer:
 *
 *   Authorization: Bearer <token>
 */
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('user', function (Request $request) {
        $user = $request->user();
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'token_abilities' => $user->currentAccessToken()?->abilities ?? [],
        ];
    })->name('api.v1.user');

    // Dashboard payload — role-aware
    Route::get('dashboard', [DashboardController::class, 'show'])->name('api.v1.dashboard');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('api.v1.notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.v1.notifications.read_all');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('api.v1.notifications.destroy');
});
