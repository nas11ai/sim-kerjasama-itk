<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AnnouncementImageController;
use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\CompleteFormBuilderController;
use App\Http\Controllers\Config\ReferenceEntityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Export\DocumentPrintController;
use App\Http\Controllers\Export\ExportController;
use App\Http\Controllers\FormAccessControlController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormEngine\SubmissionPeriodController as PeriodController;
use App\Http\Controllers\FormPhaseController;
use App\Http\Controllers\Identity\InvitationController;
use App\Http\Controllers\Identity\NidnVerificationController;
use App\Http\Controllers\Identity\ProfileCompletionController;
use App\Http\Controllers\Identity\ProfileController;
use App\Http\Controllers\Identity\ProfilePhotoController;
use App\Http\Controllers\Identity\ReviewerManagementController;
use App\Http\Controllers\Identity\UserAccessController;
use App\Http\Controllers\Identity\UserController;
use App\Http\Controllers\Monev\MonevCycleController;
use App\Http\Controllers\Notification\NotificationChannelController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Review\ReplaceReviewerController;
use App\Http\Controllers\Review\ReviewAttachmentDownloadController;
use App\Http\Controllers\Review\ReviewerAssignmentController;
use App\Http\Controllers\Review\ReviewSummaryResolutionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewerFormAssignmentController;
use App\Http\Controllers\ReviewEvaluationFormController;
use App\Http\Controllers\ReviewFormResponseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchemeController;
use App\Http\Controllers\StatController;
use App\Http\Controllers\Submission\BudgetLineItemController;
use App\Http\Controllers\Submission\ChildSubmissionController;
use App\Http\Controllers\Submission\OperatorSubmissionController;
use App\Http\Controllers\Submission\PhaseOverrideController;
use App\Http\Controllers\Submission\ResearchMemberController;
use App\Http\Controllers\Submission\ResearchOutputController;
use App\Http\Controllers\Submission\ResubmitController;
use App\Http\Controllers\Submission\SubmissionDecisionController;
use App\Http\Controllers\Submission\SubmissionFileController;
use App\Http\Controllers\Submission\SubmissionRevisionController;
use App\Http\Controllers\Submission\SubmissionWizardController;
use App\Http\Controllers\Submission\TargetOutputController;
use App\Http\Controllers\SubmissionPeriodController;
use App\Http\Controllers\SubmissionViewController;
use App\Http\Controllers\SubmissionWithdrawalController;
use App\Http\Controllers\UserDeactivationController;
use App\Http\Controllers\UserFormController;
use App\Queries\Announcement\PublicAnnouncementFeed;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('WelcomePage', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'announcements' => PublicAnnouncementFeed::run(),
    ]);
})->name('welcome');

// SHARED-02-01..04 — satu dasbor untuk semua peran (§4.4 K4).
Route::middleware(['auth', 'verified'])->get('/dashboard', DashboardController::class)
    ->name('dashboard');

// W46 — nama lama tetap ada sebagai redirect: legacy AppSidebar dan halaman legacy
// lain masih menautnya sampai F99.
Route::middleware('auth')->group(function () {
    Route::redirect('/user/dashboard', '/dashboard')->name('user.dashboard');
    Route::redirect('/reviewer/dashboard', '/dashboard')->name('reviewer.dashboard');
    Route::redirect('/admin/dashboard', '/dashboard')->name('admin.dashboard');
});

Route::get('announcements/{announcement}', [AnnouncementController::class, 'detail'])->name('announcements.detail');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // SHARED-01-06 — BR-IAM-03; terbuka bagi semua status verifikasi (BR-IAM-04, W63).
    Route::get('/profile/complete', [ProfileCompletionController::class, 'create'])->name('profile.complete');
    Route::post('/profile/complete', [ProfileCompletionController::class, 'store'])->name('profile.complete.store');

    // W65 — foto profil milik sendiri di disk privat (W15); tanpa parameter, tanpa IDOR.
    Route::get('/profile/photo', [ProfilePhotoController::class, 'show'])->name('profile.photo.show');
    Route::post('/profile/photo', [ProfilePhotoController::class, 'update'])->name('profile.photo.update');

    // BR-SM-07 — berkas jawaban pengajuan hanya terbaca lewat route ber-scope ini (W15).
    Route::get('/user/submissions/{submission}/files/{response}', [SubmissionFileController::class, 'show'])
        ->name('user.submissions.files.show');

    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');

    // File download
    Route::get('/review-attachments/download', ReviewAttachmentDownloadController::class)
        ->name('review-attachments.download');

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
});

Route::middleware(['auth'])->prefix('user')->name('user.')->group(function () {
    // W61 — biodata Form-Engine dihapus (tanpa KTP); nama lama tetap sebagai redirect sampai
    // F99 karena legacy AppSidebar masih menautnya (pola W46).
    Route::redirect('/biodata', '/profile')->name('biodata.index');

    Route::post('/announcements/{announcement}/markRead', [AnnouncementController::class, 'markAsRead'])
        ->name('announcements.markRead');

    Route::get('/announcements', [AnnouncementController::class, 'userIndex'])
        ->name('announcements.index');

    // Form Submission Routes
    Route::post('/form-submission/save-draft', [UserFormController::class, 'saveDraft'])
        ->name('form-submission.save-draft');

    Route::post('/form-submission/submit', [UserFormController::class, 'submitForm'])
        ->name('form-submission.submit');

    // ADR 0012 langkah 1 — satu-satunya jalur yang boleh melahirkan baris pengajuan.
    Route::post('/submissions', [UserFormController::class, 'startSubmission'])
        ->name('submissions.store');

    // Submission viewing routes
    // fe-v2-design §4.4 — dijaga permission DDD, bukan sekadar login.
    Route::get('/submissions', [SubmissionViewController::class, 'userIndex'])
        ->middleware('can:submissions.view-own')
        ->name('submissions.index');

    // Dideklarasikan sebelum /submissions/{submission} supaya "create" tidak terbaca sebagai id.
    Route::get('/submissions/create', [SubmissionWizardController::class, 'create'])
        ->middleware('can:submissions.create')
        ->name('submissions.create');

    Route::get('/submissions/{submission}/wizard/{step?}', [SubmissionWizardController::class, 'edit'])
        ->whereNumber('step')
        ->name('submissions.wizard');

    Route::post('/submissions/{submission}/wizard/{step}', [SubmissionWizardController::class, 'save'])
        ->whereNumber('step')
        ->name('submissions.wizard.save');

    Route::post('/submissions/{submission}/submit', [SubmissionWizardController::class, 'submit'])
        ->name('submissions.submit');

    Route::get('/submissions/{submission}/revision', SubmissionRevisionController::class)
        ->name('submissions.revision');

    Route::get('/submissions/{submission}', [SubmissionViewController::class, 'userShowSubmission'])
        ->name('submissions.show');
});

// Reviewer Routes - untuk user yang bertindak sebagai reviewer
Route::middleware(['auth'])->prefix('reviewer')->name('reviewer.')->group(function () {
    // Nama lama dipertahankan: AppSidebar dan dasbor peneliti legacy masih menautnya.
    Route::redirect('/submissions', '/reviewer/assignments')->name('submissions.index');

    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');

    // REVIEWER-03-* (S3a) — dijaga permission DDD, bukan role (§4.4 K1).
    Route::middleware('can:reviewers.evaluate')->group(function () {
        Route::get('/assignments', [ReviewerAssignmentController::class, 'index'])
            ->name('assignments.index');
        Route::get('/assignments/{submission}/evaluation', [ReviewerAssignmentController::class, 'evaluation'])
            ->name('assignments.evaluation');
        Route::get('/assignments/{submission}/summary', [ReviewerAssignmentController::class, 'summary'])
            ->name('assignments.summary');
        Route::get('/history', [ReviewerAssignmentController::class, 'history'])
            ->name('history.index');
    });
});

// Admin Routes - permission-based (users.manage; Super Admin via Gate::before)
Route::middleware(['auth', 'can:users.manage'])->prefix('admin')->name('admin.')->group(function () {
    // stat
    Route::get('/stats/form-phases', [StatController::class, 'formPhaseStatIndex'])->name('stats.form-phase');
    Route::get('/stats/form-submissions', [StatController::class, 'formSubmissionStatIndex'])->name('stats.form-submission');
    Route::get('/stats/reviewers', [StatController::class, 'submissionReviewerStatIndex'])->name('stats.reviewer');
    Route::get('/stats/users', [StatController::class, 'userStatIndex'])->name('stats.user');

    // Role Management
    Route::resource('roles', RoleController::class)->except(['show'])->names([
        'index' => 'roles.index',
        'create' => 'roles.create',
        'store' => 'roles.store',
        'edit' => 'roles.edit',
        'update' => 'roles.update',
        'destroy' => 'roles.destroy',
    ]);

    // Permission Management
    Route::resource('permissions', PermissionController::class)->except(['create', 'show', 'edit'])->names([
        'index' => 'permissions.index',
        'store' => 'permissions.store',
        'update' => 'permissions.update',
        'destroy' => 'permissions.destroy',
    ]);

    Route::resource('forms', FormController::class);
    Route::post('forms/{form}/duplicate', [FormController::class, 'duplicate'])
        ->name('forms.duplicate');
    Route::post('forms/bulk-delete', [FormController::class, 'bulkDelete'])
        ->name('forms.bulk-delete');

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

    // NEW: Reviewer form assignment management
    Route::post('/submissions/{submission}/reviewers/{reviewer}/assign-forms', [ReviewController::class, 'assignEvaluationFormsToReviewer'])
        ->name('submissions.assign-evaluation-forms');

    Route::post('/submissions/{submission}/assign-forms-bulk', [ReviewerFormAssignmentController::class, 'bulkAssignForms'])
        ->name('submissions.assign-forms-bulk');

    Route::delete('/form-assignments/{assignment}', [ReviewerFormAssignmentController::class, 'removeFormAssignment'])
        ->name('form-assignments.remove');

    // Admin create review thread
    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    // Admin add comments
    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');

    Route::resource('form-phases', FormPhaseController::class)->names([
        'index' => 'form-phases.index',
        'create' => 'form-phases.create',
        'store' => 'form-phases.store',
        'show' => 'form-phases.show',
        'edit' => 'form-phases.edit',
        'update' => 'form-phases.update',
        'destroy' => 'form-phases.destroy',
    ]);

    Route::post('form-phases/bulk-delete', [FormPhaseController::class, 'bulkDelete'])
        ->name('form-phases.bulk-delete');

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

    Route::delete('submission-periods/{submissionPeriod}', [SubmissionPeriodController::class, 'destroy'])
        ->name('submission-periods.destroy');

    // Additional routes for Form Access Controls
    Route::post('form-access-controls/bulk-create', [FormAccessControlController::class, 'bulkCreate'])
        ->name('form-access-controls.bulk-create');

    Route::post('form-access-controls/bulk-delete', [FormAccessControlController::class, 'bulkDelete'])
        ->name('form-access-controls.bulk-delete');

    // API endpoint untuk mendapatkan study programs berdasarkan faculty
    Route::get('api/study-programs', [FormAccessControlController::class, 'getStudyPrograms'])
        ->name('api.study-programs');

    Route::patch('api/form-phases/{formPhase}/status', [FormPhaseController::class, 'updateStatus'])
        ->name('form-phases.update-status');
});

// For backwards compatibility, you can add redirects for admin routes without prefix
Route::middleware(['auth', 'can:users.manage'])->group(function () {
    Route::get('/forms', function () {
        return redirect()->route('admin.forms.index');
    });
    Route::get('/form-phases', function () {
        return redirect()->route('admin.form-phases.index');
    });
    Route::get('/form-access-controls', function () {
        return redirect()->route('admin.form-access-controls.index');
    });
    Route::get('/announcements', function () {
        return redirect()->route('admin.announcements.index');
    });

    Route::get('/review-evaluation-forms', function () {
        return redirect()->route('admin.review-evaluation-forms.index');
    });
});

require __DIR__.'/auth.php';

// W47 — daftar dan detail user: users.verify atau users.manage (UserPolicy::viewAny/view).
Route::middleware(['auth', 'can:viewAny,App\\Models\\User'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])
        ->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->name('users.show')
        ->can('view', 'user');
    // BR-IAM-05/09, W57 — mengubah akun: users.manage, bukan diri sendiri, bukan Super Admin.
    Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])
        ->name('users.roles.update')
        ->can('changeRoles', 'user');
    // BR-IAM-21 — mengakhiri sesi tanpa menonaktifkan.
    Route::post('/users/{user}/sessions/end', [UserController::class, 'endSessions'])
        ->name('users.sessions.end')
        ->can('endSessions', 'user');
    // BR-IAM-20 — pengaktifan kembali, wewenang sama dengan deaktivasi.
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])
        ->name('users.reactivate')
        ->can('reactivate', 'user');
    Route::post('/users/{user}/deactivate', UserDeactivationController::class)
        ->name('users.deactivate')
        ->can('deactivate', 'user');
    Route::post('/users/{user}/permissions', [UserAccessController::class, 'grantPermission'])
        ->name('users.permissions.grant')
        ->can('managePermissions', 'user');
    Route::delete('/users/{user}/permissions', [UserAccessController::class, 'revokePermission'])
        ->name('users.permissions.revoke')
        ->can('managePermissions', 'user');
});

// BR-IAM-04/12/13, W47 — antrian verifikasi NIDN dan koreksi NIDN: users.verify, bukan
// users.manage. Tanpa berkas identitas: aplikasi tidak menyimpan KTP (W61).
Route::middleware(['auth', 'can:users.verify'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/verifications', [NidnVerificationController::class, 'index'])
        ->name('verifications.index');
    Route::post('/verifications/{profile}/verify', [NidnVerificationController::class, 'verify'])
        ->name('verifications.verify');
    Route::post('/verifications/{profile}/reject', [NidnVerificationController::class, 'reject'])
        ->name('verifications.reject');
    Route::post('/verifications/{profile}/nidn', [NidnVerificationController::class, 'correctNidn'])
        ->name('verifications.nidn');
});

// BR-IAM-16, W47 (pemilik 2026-11-02) — undangan: users.verify atau users.manage. Tanpa
// pencabutan, tautan yang bocor hanya bisa ditunggu kedaluwarsa.
Route::middleware(['auth', 'can:manage,App\\Models\\InvitationToken'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/invitations', [InvitationController::class, 'index'])
        ->name('invitations.index');
    Route::post('/invitations', [InvitationController::class, 'store'])
        ->name('invitations.store');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])
        ->name('invitations.revoke');
});

// BR-IAM-11, BR-REV-09, W47 — manajemen reviewer: reviewers.assign, bukan users.manage.
// W50 — tanpa hapus/aktifkan/nonaktifkan; masa tugas diakhiri lewat update.
Route::middleware(['auth', 'can:reviewers.assign'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reviewers', [ReviewerManagementController::class, 'index'])
        ->name('reviewers.index');
    Route::post('/reviewers', [ReviewerManagementController::class, 'store'])
        ->name('reviewers.store');
    Route::put('/reviewers/{reviewer}', [ReviewerManagementController::class, 'update'])
        ->name('reviewers.update');
});

// BR-SM-07 — layar pengajuan Operator dijaga view-all, bukan users.manage:
// pemegang view-all tanpa hak kelola pengguna tetap harus bisa membacanya (§4.4 K1).
Route::middleware(['auth', 'can:submissions.view-all'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/submissions', [OperatorSubmissionController::class, 'index'])
        ->name('submissions.index');
    Route::get('/submissions/{submission}', [OperatorSubmissionController::class, 'show'])
        ->name('submissions.show');
});

// OPERATOR-09-01 — periode dikelola pemegang periods.manage, bukan users.manage (§4.4 K1).
Route::middleware(['auth', 'can:periods.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/submission-periods', [PeriodController::class, 'index'])
        ->name('submission-periods.index');
    Route::post('/submission-periods', [PeriodController::class, 'store'])
        ->name('submission-periods.store');
    Route::put('/submission-periods/{submissionPeriod}', [PeriodController::class, 'update'])
        ->name('submission-periods.update');
});

Route::middleware(['auth', 'can:periods.manage'])->get('/submission-periods', fn () => redirect()->route('admin.submission-periods.index'));

// fe-v2-design §4.7 K5 — Operator mengelola pengumuman tanpa users.manage.
Route::middleware(['auth', 'can:announcements.manage'])->prefix('admin')->name('admin.')->group(function () {
    // K6a — gambar sisipan editor; didaftarkan sebelum resource supaya tidak tertimpa.
    Route::post('/announcements/images', AnnouncementImageController::class)
        ->name('announcements.images.store');
    // W35 — tanpa layar detail kelola; pembaca memakai `announcements.detail`.
    Route::resource('announcements', AnnouncementController::class)->except(['show']);
});

// BR-SM-11 — penarikan pengajuan. Sengaja di luar grup `can:users.manage`:
// Policy menuntut `submissions.view-all`, dan dua sumber otorisasi untuk satu
// aksi berarti tombolnya bisa tampil untuk orang yang pasti ditolak.
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/submissions/{submission}/withdraw', SubmissionWithdrawalController::class)
        ->name('submissions.withdraw');
});

/*
 * Endpoint Action M1/M2 yang sebelumnya hanya ada sebagai unit.
 *
 * Otorisasinya dipegang Policy di masing-masing controller, bukan middleware
 * grup: dua sumber otorisasi untuk satu aksi membuat objek `can` yang dikirim
 * ke frontend berbohong, dan tombolnya tampil untuk orang yang pasti ditolak
 * (ADR 0015).
 */
Route::middleware(['auth'])->group(function () {
    Route::prefix('user')->name('user.')->group(function () {
        Route::post('/submissions/{submission}/target-outputs', [TargetOutputController::class, 'store'])
            ->name('submissions.target-outputs.store');
        Route::put('/submissions/{submission}/target-outputs/{targetOutput}', [TargetOutputController::class, 'update'])
            ->name('submissions.target-outputs.update');
        // BR-SM-19 — janji yang bisa diubah harus bisa dicabut.
        Route::delete('/submissions/{submission}/target-outputs/{targetOutput}', [TargetOutputController::class, 'destroy'])
            ->name('submissions.target-outputs.destroy');
        Route::post('/submissions/{submission}/members', [ResearchMemberController::class, 'store'])
            ->name('submissions.members.store');
        // BR-SM-18 — pasangan yang selama ini tidak ada; tanpanya batas anggota
        // skema jadi jalan buntu.
        Route::delete('/submissions/{submission}/members/{member}', [ResearchMemberController::class, 'destroy'])
            ->name('submissions.members.destroy');
        // BR-SM-20 — mahasiswa tidak punya akun, jadi ia data dan bukan
        // ResearchMember.
        Route::post('/submissions/{submission}/student-members', [ResearchMemberController::class, 'storeStudent'])
            ->name('submissions.student-members.store');
        Route::delete('/submissions/{submission}/student-members/{student}', [ResearchMemberController::class, 'destroyStudent'])
            ->name('submissions.student-members.destroy');
        // BR-SM-21 — mitra yang dijanjikan saat mengajukan; PKS yang terwujud
        // dicatat terpisah sebagai ResearchOutput.
        Route::post('/submissions/{submission}/partners', [ResearchMemberController::class, 'storePartner'])
            ->name('submissions.partners.store');
        Route::delete('/submissions/{submission}/partners/{partner}', [ResearchMemberController::class, 'destroyPartner'])
            ->name('submissions.partners.destroy');
        Route::post('/submissions/{submission}/budget-items', [BudgetLineItemController::class, 'store'])
            ->name('submissions.budget-items.store');
        // BR-BUD-10 — anggaran yang bisa ditambah tapi tidak bisa dibetulkan
        // membuat BR-BUD-01 jadi jalan buntu.
        Route::put('/submissions/{submission}/budget-items/{budgetItem}', [BudgetLineItemController::class, 'update'])
            ->name('submissions.budget-items.update');
        Route::delete('/submissions/{submission}/budget-items/{budgetItem}', [BudgetLineItemController::class, 'destroy'])
            ->name('submissions.budget-items.destroy');
        // BR-SM-03 — tanpa ini alur revisi tidak punya jalan kembali.
        Route::post('/submissions/{submission}/resubmit', ResubmitController::class)
            ->name('submissions.resubmit');
        // BR-FE-04 — laporan turunan hanya lahir dari induk yang disetujui.
        Route::post('/submissions/{submission}/children', [ChildSubmissionController::class, 'store'])
            ->name('submissions.children.store');
        // BR-RO-01..09 — luaran terealisasi.
        Route::post('/submissions/{submission}/outputs', [ResearchOutputController::class, 'store'])
            ->name('submissions.outputs.store');
        Route::put('/submissions/{submission}/outputs/{output}', [ResearchOutputController::class, 'update'])
            ->name('submissions.outputs.update');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('/submissions/{submission}/reject', [SubmissionDecisionController::class, 'reject'])
            ->name('submissions.reject');
        Route::post('/submissions/{submission}/transfer-ownership', [SubmissionDecisionController::class, 'transferOwnership'])
            ->name('submissions.transfer-ownership');
        Route::post('/submissions/{submission}/reviewers/{reviewer}/replace', ReplaceReviewerController::class)
            ->name('submissions.replace-reviewer');
        // BR-REV-01 — dijaga Policy `assignReviewers` (reviewers.assign), bukan users.manage.
        Route::post('/submissions/{submission}/assign-reviewers', [ReviewController::class, 'assignReviewers'])
            ->name('submissions.assign-reviewers');
        // BR-SCH-01/02/03 — sebelum ini skema hanya bisa dibuat lewat seeder.
        Route::get('/schemes', [SchemeController::class, 'index'])
            ->name('schemes.index');
        Route::post('/schemes', [SchemeController::class, 'store'])
            ->name('schemes.store');
        Route::put('/schemes/{scheme}', [SchemeController::class, 'update'])
            ->name('schemes.update');
        Route::post('/schemes/{scheme}/deactivate', [SchemeController::class, 'deactivate'])
            ->name('schemes.deactivate');
        Route::post('/schemes/{scheme}/activate', [SchemeController::class, 'activate'])
            ->name('schemes.activate');
        // BR-IAM-08 — penghapusan unit; undangan yang belum terpakai ikut
        // tercabut alih-alih menghalangi (register #18).
        Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])
            ->name('organizations.destroy');
        // BR-IAM-14 — ambangnya user aktif, berbeda dari penghapusan.
        // BR-IAM-17, BR-IAM-18 — menyusun pohon organisasi dan memindahkan
        // orangnya. Yang kedua adalah prasyarat yang diminta BR-IAM-14.
        Route::post('/organizations', [OrganizationController::class, 'store'])
            ->name('organizations.store');
        Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])
            ->name('organizations.update');
        Route::post('/organizations/{organization}/move-user', [OrganizationController::class, 'moveUser'])
            ->name('organizations.move-user');
        Route::post('/organizations/{organization}/deactivate', [OrganizationController::class, 'deactivate'])
            ->name('organizations.deactivate');
        // BR-FE-08/09/10 — perpanjangan akses satu fase untuk satu pengajuan.
        // BR-MON-04/07/10 — pengelolaan siklus monev.
        Route::get('/submissions/{submission}/monev', [MonevCycleController::class, 'show'])
            ->name('submissions.monev.show');
        Route::post('/submissions/{submission}/monev/open', [MonevCycleController::class, 'open'])
            ->name('submissions.monev.open');
        Route::post('/submissions/{submission}/overrides', [PhaseOverrideController::class, 'store'])
            ->name('submissions.overrides.store');
        Route::delete('/submissions/{submission}/overrides/{override}', [PhaseOverrideController::class, 'destroy'])
            ->name('submissions.overrides.destroy');
    });

    Route::post('/review-summaries/{reviewSummary}/resolve', ReviewSummaryResolutionController::class)
        ->name('review-summaries.resolve');

    // BR-RPT-05 — satu rute untuk dua pembaca. Yang membedakan Operator dan
    // peneliti adalah cakupan datanya (AuditScope), bukan rutenya: dua rute
    // yang menyaring sendiri-sendiri adalah dua aturan yang akan menyimpang.
    // Tidak ada rute hapus atau sunting — append-only (BR-RPT-04) berarti
    // jalurnya memang tidak dibuat.
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    // BR-NOT-01, BR-NOT-04. "Tandai semua dibaca" (W37) mengikuti activity
    // diagram DDD: yang dilarang BR-NOT-04 adalah menandai saat halaman dibuka,
    // bukan tindakan eksplisit pengguna.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    // BR-RPT-02/03/10/12 — satu layar untuk Operator dan peneliti; yang
    // membedakan mereka SubmissionVisibility, bukan rutenya.
    Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
    Route::get('/exports/{export}/download', [ExportController::class, 'download'])
        ->middleware('signed')
        ->name('exports.download');

    // BR-RPT-11 — cetak memakai penyimpanan, retensi, dan unduhan yang sama
    // dengan ekspor; yang berbeda hanya penulisnya.
    Route::post('/submissions/{submission}/print/{document}', DocumentPrintController::class)
        ->name('submissions.print');
});

// BR-SC-01/02/03 — Konfigurasi Umum. Satu himpunan rute untuk semua data
// acuan: yang membedakan mereka adalah entri registry, bukan controller
// masing-masing, sehingga aturan hapus dan nonaktif tertulis sekali.
Route::middleware(['auth', 'can:users.manage'])->prefix('admin/reference')->name('admin.reference.')->group(function () {
    Route::get('/', [ReferenceEntityController::class, 'index'])->name('index');

    // BR-NOT-06 — hanya isi deklarasi yang bisa disunting, bukan ada-tidaknya.
    // Didaftarkan sebelum rute generik `{entity}/{id}` di bawah: kalau tidak,
    // `notification-channels/FormChanged` akan tertangkap sebagai entity
    // "notification-channels" dengan id "FormChanged".
    Route::put('/notification-channels/{eventType}', [NotificationChannelController::class, 'update'])
        ->name('notification-channels.update');

    Route::post('/{entity}', [ReferenceEntityController::class, 'store'])->name('store');
    Route::put('/{entity}/{id}', [ReferenceEntityController::class, 'update'])->name('update');
    Route::delete('/{entity}/{id}', [ReferenceEntityController::class, 'destroy'])->name('destroy');
    Route::post('/{entity}/{id}/toggle', [ReferenceEntityController::class, 'toggle'])->name('toggle');
});
