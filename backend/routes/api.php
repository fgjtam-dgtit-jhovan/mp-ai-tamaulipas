<?php

use App\Http\Controllers\CaseAnalysisController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/cases/analyze', [CaseAnalysisController::class, 'store'])
        ->name('api.case-analysis.store');

    Route::get('/cases/{id}', [CaseAnalysisController::class, 'show'])
        ->name('api.case-analysis.show');

    Route::put('/cases/{id}', [CaseAnalysisController::class, 'update'])
        ->name('api.case-analysis.update');
});
