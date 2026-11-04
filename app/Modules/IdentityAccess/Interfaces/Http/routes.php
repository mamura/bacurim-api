<?php

use App\Modules\IdentityAccess\Interfaces\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->middleware('api')->group(function (): void {
    Route::get('me', [AuthenticationController::class, 'me'])->middleware(['auth:sanctum', 'auth.session.valid'])->name('auth.me');
    Route::post('login', [AuthenticationController::class, 'login'])->middleware('throttle:identity-login')->name('auth.login');
    Route::post('logout', [AuthenticationController::class, 'logout'])->middleware(['auth:sanctum', 'auth.session.valid'])->name('auth.logout');
});
