<?php

use App\Modules\IdentityAccess\Interfaces\Http\Controllers\AuthenticationController;
use App\Modules\IdentityAccess\Interfaces\Http\Controllers\OrganizationMembersController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->middleware('api')->group(function (): void {
    Route::get('me', [AuthenticationController::class, 'me'])->middleware(['auth:sanctum', 'auth.session.valid'])->name('auth.me');
    Route::post('login', [AuthenticationController::class, 'login'])->middleware('throttle:identity-login')->name('auth.login');
    Route::post('logout', [AuthenticationController::class, 'logout'])->middleware(['auth:sanctum', 'auth.session.valid'])->name('auth.logout');
});

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum', 'auth.session.valid'])->group(function (): void {
    Route::get('auth/organizations', [OrganizationMembersController::class, 'organizations'])->name('auth.organizations');
    Route::prefix('organizations/{organization}/members')->middleware('tenant.context')->group(function (): void {
        Route::get('', [OrganizationMembersController::class, 'index'])->name('members.index');
        Route::post('', [OrganizationMembersController::class, 'store'])->name('members.store');
        Route::patch('{membership}', [OrganizationMembersController::class, 'update'])->name('members.update');
        Route::delete('{membership}', [OrganizationMembersController::class, 'destroy'])->name('members.destroy');
    });
});
