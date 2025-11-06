<?php

use App\Http\Controllers\CompleteFormBuilderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewEvaluationFormController;
use App\Http\Controllers\ReviewerFormAssignmentController;
use App\Http\Controllers\ReviewFormResponseController;
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
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BiodataController;
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
        // Ambil semua announcements public untuk carousel
        // Bisa dibatasi dengan take() jika perlu
        'announcements' => [
            'data' => Announcement::latest()
                ->with('announcementFiles')
                ->where('type', 'public')
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>', now());
                })
                ->take(10) // Batasi 10 pengumuman untuk performa carousel
                ->get(),
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 10,
            'total' => Announcement::where('type', 'public')
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>', now());
                })
                ->count(),
            'from' => 1,
            'to' => Announcement::where('type', 'public')
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>', now());
                })
                ->take(10)
                ->count(),
        ],
    ]);
})->name('welcome');

Route::get('announcements/{announcement}', [AnnouncementController::class, 'detail'])->name('announcements.detail');

Route::middleware(['auth', 'check_reviewer_status'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Enhanced review routes with evaluation integration
    Route::patch('/review-summaries/{reviewSummary}/complete', [ReviewController::class, 'completeReview'])
        ->name('review-summaries.complete');

    Route::patch('/review-summaries/{reviewSummary}/status', [ReviewController::class, 'updateReviewStatus'])
        ->name('review-summaries.update-status');

    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');

    // File download
    Route::get('/review-attachments/download', [ReviewController::class, 'downloadAttachment'])
        ->name('review-attachments.download');

    Route::get('/submissions', [UserFormController::class, 'reviewerSubmissions'])
        ->name('submissions.index');

    // Enhanced submission status update
    Route::patch('/submissions/{submission}/status', [ReviewController::class, 'updateSubmissionStatus'])
        ->name('submissions.update-status');

    // NEW: Evaluation form routes for reviewers
    Route::prefix('reviewer/evaluation-forms')->name('reviewer.evaluation-form.')->group(function () {
        // NEW: Start or continue evaluation form (auto-create assignment)
        // This is the key route that enables auto-assignment
        Route::post('/start', [ReviewFormResponseController::class, 'showOrCreate'])
            ->name('start');

        // Show evaluation form (existing or newly created)
        Route::get('/{assignment}', [ReviewFormResponseController::class, 'show'])
            ->name('show');

        // Save draft
        Route::post('/{assignment}/save-draft', [ReviewFormResponseController::class, 'saveDraft'])
            ->name('save-draft');

        // Submit evaluation
        Route::post('/{assignment}/submit', [ReviewFormResponseController::class, 'submit'])
            ->name('submit');

        // View submitted evaluation
        Route::get('/{assignment}/submitted', [ReviewFormResponseController::class, 'showSubmitted'])
            ->name('submitted');

        // Download summary
        Route::get('/{assignment}/download-summary', [ReviewFormResponseController::class, 'downloadSummary'])
            ->name('download-summary');

        // Get progress (optional - for ajax calls)
        Route::get('/{assignment}/progress', [ReviewFormResponseController::class, 'getProgress'])
            ->name('progress');
    });

    // NEW: Reviewer assignments management
    Route::get('/reviewer/assignments', [ReviewerFormAssignmentController::class, 'getMyAssignments'])
        ->name('reviewer.assignments.index');
});

Route::middleware(['auth'])->prefix('user')->name('user.')->group(function () {
    // khusus form biodata
    Route::get('/biodata', [BiodataController::class, 'showBiodataForm'])->name('biodata.index');
    Route::post('/biodata', [BiodataController::class, 'submitForm'])->name('biodata.submit');
});

Route::middleware(['auth', 'check_biodata', 'check_reviewer_status'])->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserFormController::class, 'dashboard'])
        ->name('dashboard');

    Route::get('/announcements', [AnnouncementController::class, 'userIndex'])
        ->name('announcements.index');

    Route::get('/announcements/{announcement}/markRead', [AnnouncementController::class, 'markAsRead'])
        ->name('announcements.markRead');

    // Form Phase Routes
    Route::get('/submission-period/{period}/form-phase/{phase}', [UserFormController::class, 'showFormPhase'])
        ->name('form-phase');

    // Form Submission Routes
    Route::post('/form-submission/save-draft', [UserFormController::class, 'saveDraft'])
        ->name('form-submission.save-draft');

    Route::post('/form-submission/submit', [UserFormController::class, 'submitForm'])
        ->name('form-submission.submit');

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

// Reviewer Routes - untuk user yang bertindak sebagai reviewer
Route::middleware(['auth', 'check_reviewer_status'])->prefix('reviewer')->name('reviewer.')->group(function () {
    // Dashboard khusus reviewer
    Route::get('/dashboard', [SubmissionViewController::class, 'reviewerDashboard'])
        ->name('dashboard');

    // List submissions yang di-assign untuk review
    Route::get('/submissions', [SubmissionViewController::class, 'reviewerSubmissions'])
        ->name('submissions.index');

    Route::get('/submissions/{submission}', [SubmissionViewController::class, 'reviewerShowSubmission'])
        ->name('submissions.show');

    // Enhanced review actions with evaluation context
    Route::patch('/review-summaries/{reviewSummary}/complete', [ReviewController::class, 'completeReview'])
        ->name('review-summaries.complete');

    Route::patch('/review-summaries/{reviewSummary}/status', [ReviewController::class, 'updateReviewStatus'])
        ->name('review-summaries.update-status');

    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');
});

// Admin Routes - only accessible by Super Admin or Admin role
Route::middleware(['auth', 'role:Super Admin|Admin', 'check_reviewer_status'])->prefix('admin')->name('admin.')->group(function () {
    // Admin Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users/store', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'delete'])->name('users.delete');

    // Role Management
    Route::resource('roles', RoleController::class)->names([
        'index' => 'roles.index',
        'create' => 'roles.create',
        'store' => 'roles.store',
        'edit' => 'roles.edit',
        'update' => 'roles.update',
        'destroy' => 'roles.destroy',
    ]);

    // Permission Management
    Route::resource('permissions', PermissionController::class)->names([
        'index' => 'permissions.index',
        'create' => 'permissions.create',
        'store' => 'permissions.store',
        'show' => 'permissions.show',
        'edit' => 'permissions.edit',
        'update' => 'permissions.update',
        'destroy' => 'permissions.destroy',
    ]);

    // Assigning & Revoke Role for Users
    Route::post('users/{user}/assign-role', [UserController::class, 'assignRole'])
        ->name('users.assign-role');
    Route::delete('users/{user}/revoke-role', [UserController::class, 'revokeRole'])
        ->name('users.revoke-role');

    // Assigning & Revoke Permissions for Roles
    Route::post('users/{user}/give-permission', [RoleController::class, 'givePermissions'])
        ->name('users.assign-permissions');
    Route::delete('users/{user}/revoke-permission', [RoleController::class, 'revokePermissions'])
        ->name('users.revoke-permissions');

    Route::resource('forms', FormController::class);
    Route::post('forms/{form}/duplicate', [FormController::class, 'duplicate'])
        ->name('forms.duplicate');

    // Complete Form Builder Routes
    Route::get('/form-builder/create', [CompleteFormBuilderController::class, 'create'])
        ->name('form-builder.create');
    Route::post('/form-builder/store', [CompleteFormBuilderController::class, 'store'])
        ->name('form-builder.store');

    // NEW: Review Evaluation Forms Management
    Route::resource('review-evaluation-forms', ReviewEvaluationFormController::class)->names([
        'index' => 'review-evaluation-forms.index',
        'create' => 'review-evaluation-forms.create',
        'store' => 'review-evaluation-forms.store',
        'show' => 'review-evaluation-forms.show',
        'edit' => 'review-evaluation-forms.edit',
        'update' => 'review-evaluation-forms.update',
        'destroy' => 'review-evaluation-forms.destroy',
    ]);

    // NEW: Additional review evaluation form routes
    Route::post('review-evaluation-forms/{reviewEvaluationForm}/duplicate', [ReviewEvaluationFormController::class, 'duplicate'])
        ->name('review-evaluation-forms.duplicate');

    Route::post('review-evaluation-forms/update-order', [ReviewEvaluationFormController::class, 'updateOrder'])
        ->name('review-evaluation-forms.update-order');

    Route::get('review-evaluation-forms/{reviewEvaluationForm}/preview', [ReviewEvaluationFormController::class, 'preview'])
        ->name('review-evaluation-forms.preview');

    // Submission viewing routes
    Route::get('/submissions', [SubmissionViewController::class, 'adminIndex'])
        ->name('submissions.index');

    Route::get('/submissions/period/{period}', [SubmissionViewController::class, 'adminShowPeriod'])
        ->name('submissions.period');

    Route::get('/submissions/{submission}', [SubmissionViewController::class, 'adminShowSubmission'])
        ->name('submissions.show');

    // Enhanced reviewer assignment with evaluation forms
    Route::post('/submissions/{submission}/assign-reviewers', [ReviewController::class, 'assignReviewers'])
        ->name('submissions.assign-reviewers');

    Route::delete('/submissions/{submission}/reviewers/{reviewer}', [ReviewController::class, 'removeReviewer'])
        ->name('submissions.remove-reviewer');

    // Enhanced available reviewers with evaluation context
    Route::get('/submissions/{submission}/available-reviewers', [ReviewController::class, 'getAvailableReviewers'])
        ->name('submissions.available-reviewers');

    // NEW: Evaluation-specific routes
    Route::get('/submissions/{submission}/available-evaluation-forms', [ReviewController::class, 'getAvailableEvaluationForms'])
        ->name('submissions.available-evaluation-forms');

    Route::get('/submissions/{submission}/evaluation-status', [ReviewController::class, 'getEvaluationStatus'])
        ->name('submissions.evaluation-status');

    // NEW: Reviewer form assignment management
    Route::post('/submissions/{submission}/reviewers/{reviewer}/assign-forms', [ReviewController::class, 'assignEvaluationFormsToReviewer'])
        ->name('submissions.assign-evaluation-forms');

    Route::post('/submissions/{submission}/assign-forms-bulk', [ReviewerFormAssignmentController::class, 'bulkAssignForms'])
        ->name('submissions.assign-forms-bulk');

    Route::get('/submissions/{submission}/form-assignments', [ReviewerFormAssignmentController::class, 'getAssignmentsForSubmission'])
        ->name('submissions.form-assignments');

    Route::delete('/form-assignments/{assignment}', [ReviewerFormAssignmentController::class, 'removeFormAssignment'])
        ->name('form-assignments.remove');

    Route::patch('/form-assignments/{assignment}', [ReviewerFormAssignmentController::class, 'updateAssignment'])
        ->name('form-assignments.update');

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

    Route::get('form-phases/{formPhase}/evaluation-forms', [FormPhaseController::class, 'evaluationForms'])
        ->name('form-phases.evaluation-forms');

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
        'create' => 'announcements.create',
        'store' => 'announcements.store',
        'show' => 'announcements.show',
        'edit' => 'announcements.edit',
        'update' => 'announcements.update',
        'destroy' => 'announcements.destroy',
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

    // NEW: Maintenance routes
    Route::post('maintenance/auto-lock-overdue-assignments', [ReviewerFormAssignmentController::class, 'autoLockOverdueAssignments'])
        ->name('maintenance.auto-lock-overdue');
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
    Route::get('/announcements', function () {
        return redirect()->route('admin.announcements.index');
    });

    Route::get('/review-evaluation-forms', function () {
        return redirect()->route('admin.review-evaluation-forms.index');
    });
});

require __DIR__ . '/auth.php';
