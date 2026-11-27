<?php

use App\Modules\Financial\Interfaces\Http\Controllers\FinancialAccountsController;
use App\Modules\Financial\Interfaces\Http\Controllers\FinancialCategoriesController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/organizations/{organization}')->middleware(['api', 'auth:sanctum', 'auth.session.valid', 'tenant.context'])->group(function (): void {
    Route::post('financial-categories/initialize', [FinancialCategoriesController::class, 'initialize'])->name('financial.categories.initialize');
    Route::get('financial-categories', [FinancialCategoriesController::class, 'index'])->name('financial.categories.index');
    Route::post('financial-categories', [FinancialCategoriesController::class, 'store'])->name('financial.categories.store');
    Route::get('financial-categories/{category}', [FinancialCategoriesController::class, 'show'])->whereUuid('category')->name('financial.categories.show');
    Route::patch('financial-categories/{category}', [FinancialCategoriesController::class, 'update'])->whereUuid('category')->name('financial.categories.update');
    Route::get('financial-accounts', [FinancialAccountsController::class, 'index'])->name('financial.accounts.index');
    Route::post('financial-accounts', [FinancialAccountsController::class, 'store'])->name('financial.accounts.store');
    Route::get('financial-accounts/{account}', [FinancialAccountsController::class, 'show'])->whereUuid('account')->name('financial.accounts.show');
    Route::patch('financial-accounts/{account}', [FinancialAccountsController::class, 'update'])->whereUuid('account')->name('financial.accounts.update');
});
