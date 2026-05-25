<?php

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
});
