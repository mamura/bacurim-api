<?php

use App\Modules\Financial\Interfaces\Http\Controllers\EconomicEventsController;
use App\Modules\Financial\Interfaces\Http\Controllers\FinancialAccountsController;
use App\Modules\Financial\Interfaces\Http\Controllers\FinancialCategoriesController;
use App\Modules\Financial\Interfaces\Http\Controllers\FinancialCommitmentsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/organizations/{organization}')->middleware(['api', 'auth:sanctum', 'auth.session.valid', 'tenant.context'])->group(function (): void {
    Route::get('economic-events', [EconomicEventsController::class, 'index'])->name('financial.events.index');
    Route::post('economic-events', [EconomicEventsController::class, 'store'])->name('financial.events.store');
    Route::get('economic-events/{event}', [EconomicEventsController::class, 'show'])->whereUuid('event')->name('financial.events.show');
    Route::patch('economic-events/{event}', [EconomicEventsController::class, 'update'])->whereUuid('event')->name('financial.events.update');
    Route::get('economic-events/{event}/financial-commitments', [FinancialCommitmentsController::class, 'forEvent'])->whereUuid('event')->name('financial.events.commitments');
    Route::get('financial-commitments', [FinancialCommitmentsController::class, 'index'])->name('financial.commitments.index');
    Route::get('financial-commitments/{commitment}', [FinancialCommitmentsController::class, 'show'])->whereUuid('commitment')->name('financial.commitments.show');
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
