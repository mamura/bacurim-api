<?php

use App\Modules\Financial\Interfaces\Http\Controllers\FinancialAccountsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/organizations/{organization}')->middleware(['api', 'auth:sanctum', 'auth.session.valid', 'tenant.context'])->group(function (): void {
    Route::get('financial-accounts', [FinancialAccountsController::class, 'index'])->name('financial.accounts.index');
    Route::post('financial-accounts', [FinancialAccountsController::class, 'store'])->name('financial.accounts.store');
    Route::get('financial-accounts/{account}', [FinancialAccountsController::class, 'show'])->whereUuid('account')->name('financial.accounts.show');
    Route::patch('financial-accounts/{account}', [FinancialAccountsController::class, 'update'])->whereUuid('account')->name('financial.accounts.update');
});
