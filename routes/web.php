<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/panel', [DocumentController::class, 'index'])->name('dashboard');

    Route::prefix('documentos')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::get('/nuevo', [DocumentController::class, 'create'])->name('create');
        Route::get('/personas/buscar', [DocumentController::class, 'searchPersons'])->name('persons.search');
        Route::post('/analizar', [DocumentController::class, 'analyze'])->name('analyze');
        Route::get('/revisar/{token}', [DocumentController::class, 'review'])->name('review');
        Route::post('/revisar/{token}/confirmar', [DocumentController::class, 'confirm'])->name('confirm');
        Route::post('/revisar/{token}/cancelar', [DocumentController::class, 'cancel'])->name('cancel');
        Route::get('/{person}', [DocumentController::class, 'show'])->name('show');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
