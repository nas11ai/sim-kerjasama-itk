<?php

use App\Http\Controllers\FacultyController;
use App\Http\Controllers\FormAccessControlController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormPhaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubmissionPeriodController;
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

Route::resource('form-access-controls', FormAccessControlController::class)->names([
    'index' => 'form-access-controls.index',
    'create' => 'form-access-controls.create',
    'store' => 'form-access-controls.store',
    'show' => 'form-access-controls.show',
    'edit' => 'form-access-controls.edit',
    'update' => 'form-access-controls.update',
    'destroy' => 'form-access-controls.destroy',
]);

Route::resource('submission-periods', SubmissionPeriodController::class)->names([
    'index' => 'submission-periods.index',
    'create' => 'submission-periods.create',
    'store' => 'submission-periods.store',
    'show' => 'submission-periods.show',
    'edit' => 'submission-periods.edit',
    'update' => 'submission-periods.update',
    'destroy' => 'submission-periods.destroy',
]);

Route::resource('faculties', FacultyController::class)->names([
    'index' => 'faculties.index',
    'create' => 'faculties.create',
    'store' => 'faculties.store',
    'show' => 'faculties.show',
    'edit' => 'faculties.edit',
    'update' => 'faculties.update',
    'destroy' => 'faculties.destroy',
]);

// Study Program Routes
Route::get('study-programs', [FacultyController::class, 'studyPrograms'])
    ->name('faculties.study-programs');

Route::get('study-programs/create', [FacultyController::class, 'createStudyProgram'])
    ->name('faculties.study-programs.create');

Route::post('study-programs', [FacultyController::class, 'storeStudyProgram'])
    ->name('faculties.study-programs.store');

Route::get('study-programs/{studyProgram}/edit', [FacultyController::class, 'editStudyProgram'])
    ->name('faculties.study-programs.edit');

Route::put('study-programs/{studyProgram}', [FacultyController::class, 'updateStudyProgram'])
    ->name('faculties.study-programs.update');

Route::delete('study-programs/{studyProgram}', [FacultyController::class, 'destroyStudyProgram'])
    ->name('faculties.study-programs.destroy');

Route::post('/submission-date-labels', [SubmissionPeriodController::class, 'storeLabel'])
    ->name('submission-date-labels.store');

// Additional routes for Form Access Controls
Route::post('form-access-controls/bulk-create', [FormAccessControlController::class, 'bulkCreate'])
    ->name('form-access-controls.bulk-create');

Route::post('form-access-controls/bulk-delete', [FormAccessControlController::class, 'bulkDelete'])
    ->name('form-access-controls.bulk-delete');

// API endpoint untuk mendapatkan study programs berdasarkan faculty
Route::get('api/study-programs', [FormAccessControlController::class, 'getStudyPrograms'])
    ->name('api.study-programs');

// API endpoint untuk mendapatkan form access controls
Route::get('api/form-access-controls', [FormPhaseController::class, 'getFormAccessControls'])
    ->name('api.form-access-controls');

Route::patch('api/form-phases/{formPhase}/status', [FormPhaseController::class, 'updateStatus'])
    ->name('form-phases.update-status');

require __DIR__ . '/auth.php';
