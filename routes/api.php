<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * License activation — unauthenticated; the license key is the
 * credential. ResolveTenant scopes the key lookup to the store whose
 * host is called (acme.example.com/api/v1/licenses/activate), and the
 * `license-api` limiter (AppServiceProvider) caps calls per IP.
 */
Route::prefix('v1/licenses')
    ->middleware([ResolveTenant::class, 'throttle:license-api'])
    ->name('api.v1.licenses.')
    ->group(function () {
        Route::post('activate', [LicenseController::class, 'activate'])->name('activate');
        Route::post('deactivate', [LicenseController::class, 'deactivate'])->name('deactivate');
        Route::get('validate', [LicenseController::class, 'validate'])->name('validate');
    });

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
