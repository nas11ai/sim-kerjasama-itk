<?php

use App\Http\Controllers\ReviewerRoleController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\FormAccessControlController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormPhaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewerController;
use App\Http\Controllers\SubmissionPeriodController;
use App\Http\Controllers\SubmissionViewController;
use App\Http\Controllers\UserFormController;
use App\Http\Controllers\AnnouncementController;
use Illuminate\Foundation\Application;
use App\Models\Announcement;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'announcements' => Announcement::latest()->get(),
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserFormController::class, 'dashboard'])
        ->name('dashboard');
    // Form Phase Routes
    Route::get('/submission-period/{period}/form-phase/{phase}', [UserFormController::class, 'showFormPhase'])
        ->name('form-phase');

    // Form Submission Routes
    Route::post('/form-submission/save-draft', [UserFormController::class, 'saveDraft'])
        ->name('form-submission.save-draft');

    Route::post('/form-submission/submit', [UserFormController::class, 'submitForm'])
        ->name('form-submission.submit');

    // Get existing form data for editing
    Route::get('/form-submission/data', [UserFormController::class, 'getFormSubmissionData'])
        ->name('form-submission.data');

    // Submission viewing routes
    Route::get('/submissions', [SubmissionViewController::class, 'userIndex'])
        ->name('submissions.index');

    Route::get('/submissions/period/{period}', [SubmissionViewController::class, 'userShowPeriod'])
        ->name('submissions.period');

    Route::get('/submissions/{submission}', [SubmissionViewController::class, 'userShowSubmission'])
        ->name('submissions.show');
});

// Admin Routes - only accessible by Super Admin or Admin role
Route::middleware(['auth', 'role:Super Admin|Admin'])->prefix('admin')->name('admin.')->group(function () {
    // Admin Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    Route::resource('forms', FormController::class);

    // Submission viewing routes
    Route::get('/submissions', [SubmissionViewController::class, 'adminIndex'])
        ->name('submissions.index');

    Route::get('/submissions/period/{period}', [SubmissionViewController::class, 'adminShowPeriod'])
        ->name('submissions.period');

    Route::get('/submissions/{submission}', [SubmissionViewController::class, 'adminShowSubmission'])
        ->name('submissions.show');

    // Reviewer Management
    Route::resource('reviewers', ReviewerController::class);
    Route::patch('reviewers/{reviewer}/deactivate', [ReviewerController::class, 'deactivate'])
        ->name('reviewers.deactivate');
    Route::patch('reviewers/{reviewer}/activate', [ReviewerController::class, 'activate'])
        ->name('reviewers.activate');

    // Reviewer Role Management
    Route::resource('reviewer-roles', ReviewerRoleController::class);
    Route::patch('reviewer-roles/{reviewerRole}/toggle-status', [ReviewerRoleController::class, 'toggleStatus'])
        ->name('reviewer-roles.toggle-status');

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

    Route::resource('announcements', AnnouncementController::class)->names([
        'index' => 'announcements.index',
        'create' => 'announcements.create', //belum ada
        'store' => 'announcements.store', //belum ada
        'show' => 'announcements.show', //belum ada
        'edit' => 'announcements.edit', //belum ada
        'update' => 'announcements.update', //belum ada
        'destroy' => 'announcements.destroy', //belum ada
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
});

// For backwards compatibility, you can add redirects for admin routes without prefix
Route::middleware(['auth', 'role:Super Admin|Admin'])->group(function () {
    Route::get('/forms', function () {
        return redirect()->route('admin.forms.index');
    });
    Route::get('/form-phases', function () {
        return redirect()->route('admin.form-phases.index');
    });
    Route::get('/form-access-controls', function () {
        return redirect()->route('admin.form-access-controls.index');
    });
    Route::get('/submission-periods', function () {
        return redirect()->route('admin.submission-periods.index');
    });
    Route::get('/faculties', function () {
        return redirect()->route('admin.faculties.index');
    });
    Route::get('/announcements', function () {
        return redirect()->route('admin.announcements.index');
    });
});

require __DIR__ . '/auth.php';
