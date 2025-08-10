<?php

use App\Http\Controllers\FormController;
use App\Http\Controllers\FormPhaseController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::resource('forms', FormController::class);

Route::resource('form-phases', FormPhaseController::class)->names([
    'index' => 'form-phases.index',
    'create' => 'form-phases.create',
    'store' => 'form-phases.store',
    'show' => 'form-phases.show',
    'edit' => 'form-phases.edit',
    'update' => 'form-phases.update',
    'destroy' => 'form-phases.destroy',
]);

// API endpoint untuk mendapatkan form access controls
Route::get('api/form-access-controls', [FormPhaseController::class, 'getFormAccessControls'])
    ->name('api.form-access-controls');

Route::patch('api/form-phases/{formPhase}/status', [FormPhaseController::class, 'updateStatus'])
    ->name('form-phases.update-status');

require __DIR__ . '/auth.php';
