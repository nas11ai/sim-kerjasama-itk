<?php

use App\Http\Controllers\ReviewController;
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

Route::middleware(['auth', 'check_reviewer_status'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Complete review - untuk assigned reviewer saja
    Route::patch('/review-summaries/{reviewSummary}/complete', [ReviewController::class, 'completeReview'])
        ->name('review-summaries.complete');

    // Update review status - untuk assigned reviewer atau admin
    Route::patch('/review-summaries/{reviewSummary}/status', [ReviewController::class, 'updateReviewStatus'])
        ->name('review-summaries.update-status');

    // Create thread - untuk submission owner, assigned reviewer, atau admin
    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    // Add comments - untuk submission owner, assigned reviewer, atau admin
    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');

    // File download
    Route::get('/review-attachments/download', [ReviewController::class, 'downloadAttachment'])
        ->name('review-attachments.download');

    Route::get('/submissions', [UserFormController::class, 'reviewerSubmissions'])
        ->name('submissions.index');
});

Route::middleware(['auth', 'check_reviewer_status'])->prefix('user')->name('user.')->group(function () {
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

    // Review routes dengan prefix user
    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');
});

// Reviewer Routes - untuk user yang bertindak sebagai reviewer
Route::middleware(['auth', 'check_reviewer_status'])->prefix('reviewer')->name('reviewer.')->group(function () {

    // Dashboard khusus reviewer (optional)
    Route::get('/dashboard', [SubmissionViewController::class, 'reviewerDashboard'])
        ->name('dashboard');

    // List submissions yang di-assign untuk review
    Route::get('/submissions', [SubmissionViewController::class, 'reviewerSubmissions'])
        ->name('submissions.index');

    // Complete review
    Route::patch('/review-summaries/{reviewSummary}/complete', [ReviewController::class, 'completeReview'])
        ->name('review-summaries.complete');

    // Update review status
    Route::patch('/review-summaries/{reviewSummary}/status', [ReviewController::class, 'updateReviewStatus'])
        ->name('review-summaries.update-status');

    // Create thread sebagai reviewer
    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    // Add comments sebagai reviewer
    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');
});

// Admin Routes - only accessible by Super Admin or Admin role
Route::middleware(['auth', 'role:Super Admin|Admin', 'check_reviewer_status'])->prefix('admin')->name('admin.')->group(function () {
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

    // Reviewer assignment (hanya admin)
    Route::post('/submissions/{submission}/assign-reviewers', [ReviewController::class, 'assignReviewers'])
        ->name('submissions.assign-reviewers');

    Route::delete('/submissions/{submission}/reviewers/{reviewer}', [ReviewController::class, 'removeReviewer'])
        ->name('submissions.remove-reviewer');

    // Get available reviewers
    Route::get('/submissions/{submission}/available-reviewers', [ReviewController::class, 'getAvailableReviewers'])
        ->name('submissions.available-reviewers');

    // Admin create review thread
    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    // Admin update review status
    Route::patch('/review-summaries/{reviewSummary}/status', [ReviewController::class, 'updateReviewStatus'])
        ->name('review-summaries.update-status');

    // Admin delete review thread
    Route::delete('/review-summaries/{reviewSummary}', [ReviewController::class, 'deleteReviewThread'])
        ->name('review-summaries.destroy');

    // Admin add comments
    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');

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
Route::middleware(['auth', 'role:Super Admin|Admin', 'check_reviewer_status'])->group(function () {
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
});

require __DIR__ . '/auth.php';
