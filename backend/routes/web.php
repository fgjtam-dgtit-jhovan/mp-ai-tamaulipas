<?php

use App\Http\Controllers\CaseAnalysisController;
use App\Http\Controllers\CaseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'auth/Login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('/case-analysis', [CaseAnalysisController::class, 'index'])->name('case-analysis.index');
    Route::get('/case-analysis/{id}', [CaseAnalysisController::class, 'show'])->name('case-analysis.show');
    Route::put('/case-analysis/{id}', [CaseAnalysisController::class, 'update'])->name('case-analysis.update');
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/{expediente}/{idCarpeta}', [CaseController::class, 'show'])
        ->where('expediente', '.*')
        ->name('cases.show');
    Route::post('/cases/analyze', [CaseController::class, 'analyze'])->name('cases.analyze');
});

// Route::post('/v1/cases/analyze', [CaseAnalysisController::class, 'store'])->name('case-analysis.store');

require __DIR__.'/settings.php';
