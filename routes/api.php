<?php

use App\Http\Controllers\Api\V1\TransactionDemoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── MySQL Transaction Demo ────────────────────────────────────────────────
    Route::prefix('demo/transactions')->name('demo.transactions.')->group(function () {
        Route::get('accounts', [TransactionDemoController::class, 'accounts'])->name('accounts');
        Route::post('commit',    [TransactionDemoController::class, 'commit'])->name('commit');
        Route::post('rollback',  [TransactionDemoController::class, 'rollback'])->name('rollback');
        Route::post('savepoint', [TransactionDemoController::class, 'savepoint'])->name('savepoint');
    });

});
