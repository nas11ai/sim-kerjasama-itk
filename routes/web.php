<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AnnouncementImageController;
use App\Http\Controllers\Config\ReferenceEntityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Export\DocumentPrintController;
use App\Http\Controllers\FormEngine\EvaluationFormController;
use App\Http\Controllers\FormEngine\FormAccessController;
use App\Http\Controllers\FormEngine\FormController;
use App\Http\Controllers\FormEngine\FormPhaseController;
use App\Http\Controllers\FormEngine\SubmissionPeriodController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Identity\InvitationController;
use App\Http\Controllers\Identity\NidnVerificationController;
use App\Http\Controllers\Identity\OrganizationController;
use App\Http\Controllers\Identity\ProfileCompletionController;
use App\Http\Controllers\Identity\ProfileController;
use App\Http\Controllers\Identity\ProfilePhotoController;
use App\Http\Controllers\Identity\ReviewerManagementController;
use App\Http\Controllers\Identity\UserAccessController;
use App\Http\Controllers\Identity\UserController;
use App\Http\Controllers\Monev\MonevController;
use App\Http\Controllers\Monev\MonevManageController;
use App\Http\Controllers\Monev\MonevReportController;
use App\Http\Controllers\Notification\NotificationChannelController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Reporting\AuditController;
use App\Http\Controllers\Reporting\ExportController;
use App\Http\Controllers\Reporting\StatisticsController;
use App\Http\Controllers\ResearchOutput\ResearchOutputController;
use App\Http\Controllers\ResearchOutput\ResearchOutputFileController;
use App\Http\Controllers\Review\ReplaceReviewerController;
use App\Http\Controllers\Review\ReviewAttachmentDownloadController;
use App\Http\Controllers\Review\ReviewerAssignmentController;
use App\Http\Controllers\Review\ReviewRoundController;
use App\Http\Controllers\Review\ReviewSummaryResolutionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewFormResponseController;
use App\Http\Controllers\Scheme\SchemeController;
use App\Http\Controllers\Submission\BudgetLineItemController;
use App\Http\Controllers\Submission\OperatorSubmissionController;
use App\Http\Controllers\Submission\PhaseOverrideController;
use App\Http\Controllers\Submission\ResearchMemberController;
use App\Http\Controllers\Submission\ResubmitController;
use App\Http\Controllers\Submission\SubmissionDecisionController;
use App\Http\Controllers\Submission\SubmissionFileController;
use App\Http\Controllers\Submission\SubmissionRevisionController;
use App\Http\Controllers\Submission\SubmissionWizardController;
use App\Http\Controllers\Submission\TargetOutputController;
use App\Http\Controllers\SubmissionViewController;
use App\Http\Controllers\SubmissionWithdrawalController;
use App\Http\Controllers\UserDeactivationController;
use App\Http\Controllers\UserFormController;
use Illuminate\Support\Facades\Route;

// W157 — tanpa layar sambutan (K3): umpan pengumuman publik ada di halaman login sejak A1.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

// SHARED-02-01..04 — satu dasbor untuk semua peran (§4.4 K4).
Route::middleware(['auth', 'verified'])->get('/dashboard', DashboardController::class)
    ->name('dashboard');

Route::get('announcements/{announcement}', [AnnouncementController::class, 'detail'])->name('announcements.detail');

Route::middleware(['auth'])->group(function () {
    // K2 (W171) — JSON internal command palette; bersesi sama dengan halaman, bukan API publik.
    Route::get('/cari', GlobalSearchController::class)->middleware('throttle:60,1')->name('search');

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

    // W159 — satu route komentar untuk reviewer, Operator, dan peneliti; Action yang memutuskan.
    Route::post('/review-summaries/{reviewSummary}/comments', [ReviewController::class, 'addComment'])
        ->name('review-comments.store');

    // File download
    Route::get('/review-attachments/download', ReviewAttachmentDownloadController::class)
        ->name('review-attachments.download');

    // REVIEWER-03-02 — aksi tulis lembar evaluasi; bacaannya di reviewer.assignments.evaluation.
    Route::prefix('reviewer/evaluation-forms')->name('reviewer.evaluation-form.')->group(function () {
        Route::post('/start', [ReviewFormResponseController::class, 'showOrCreate'])
            ->name('start');
        Route::post('/{assignment}/save-draft', [ReviewFormResponseController::class, 'saveDraft'])
            ->name('save-draft');
        Route::post('/{assignment}/submit', [ReviewFormResponseController::class, 'submit'])
            ->name('submit');
    });
});

Route::middleware(['auth'])->prefix('user')->name('user.')->group(function () {
    Route::post('/announcements/{announcement}/markRead', [AnnouncementController::class, 'markAsRead'])
        ->name('announcements.markRead');

    Route::get('/announcements', [AnnouncementController::class, 'userIndex'])
        ->name('announcements.index');

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

// W130 — Monev peneliti (ux/ia/05_monev). BR-SM-04 — hanya ketua induk yang memulai laporan.
Route::middleware(['auth'])->prefix('monev')->name('monev.')->group(function () {
    Route::get('/', [MonevController::class, 'index'])
        ->middleware('can:submissions.view-own')
        ->name('index');
    Route::post('/{submission}/cycles/{detail}', [MonevController::class, 'store'])
        ->middleware('can:startMonevReport,submission')
        ->name('reports.store');
    Route::get('/reports/{report}/fill', [MonevReportController::class, 'edit'])
        ->name('reports.edit');
    Route::post('/reports/{report}/draft', [MonevReportController::class, 'save'])
        ->name('reports.save');
    Route::post('/reports/{report}/submit', [MonevReportController::class, 'submit'])
        ->name('reports.submit');
    Route::get('/reports/{report}', [MonevReportController::class, 'show'])
        ->name('reports.show');
});

// W145 — luaran penelitian (ux/ia/06_research_output). Siapa/kapan/keadaan: OutputWriteAccess (W146).
Route::middleware(['auth'])->prefix('research-outputs')->name('research-outputs.')->group(function () {
    Route::get('/', [ResearchOutputController::class, 'index'])->name('index');
    Route::get('/create', [ResearchOutputController::class, 'create'])->name('create');
    // BR-RO-01..09 — luaran terealisasi. Tidak ada route hapus (BR-RO-06, RO-OUTPUT-23).
    Route::post('/submissions/{submission}', [ResearchOutputController::class, 'store'])->name('store');
    Route::get('/{output}', [ResearchOutputController::class, 'show'])->whereNumber('output')->name('show');
    Route::get('/{output}/edit', [ResearchOutputController::class, 'edit'])->whereNumber('output')->name('edit');
    Route::put('/{output}', [ResearchOutputController::class, 'update'])->whereNumber('output')->name('update');
    // W141 — berkas bukti luaran hanya terbaca lewat route ber-scope ini.
    Route::get('/{output}/files/{file}', [ResearchOutputFileController::class, 'show'])
        ->whereNumber('file')
        ->name('files.show');
});

// W129 — kelola siklus monev: membaca semua pengajuan (BR-SM-07), bukan periods.manage.
Route::middleware(['auth', 'can:submissions.view-all'])->prefix('monev/manage')->name('monev.manage.')->group(function () {
    Route::get('/', [MonevManageController::class, 'index'])->name('index');
    Route::get('/{submission}', [MonevManageController::class, 'show'])->name('show');
    // BR-MON-10 — aktivasi sekali per siklus (W126).
    Route::post('/{submission}/cycles/{detail}/open', [MonevManageController::class, 'open'])->name('open');
    // BR-MON-04 — penugasan manual per siklus; Policy assignMonevReviewer di FormRequest.
    Route::post('/{submission}/cycles/{detail}/reviewers', [MonevManageController::class, 'assignReviewer'])
        ->name('reviewers.store');
});

// Reviewer Routes - untuk user yang bertindak sebagai reviewer
Route::middleware(['auth'])->prefix('reviewer')->name('reviewer.')->group(function () {
    Route::post('/submissions/{submission}/review-threads', [ReviewController::class, 'createReviewThread'])
        ->name('review-threads.store');

    // REVIEWER-03-* (S3a) — dijaga permission DDD, bukan role (§4.4 K1).
    Route::middleware('can:reviewers.evaluate')->group(function () {
        Route::get('/assignments', [ReviewerAssignmentController::class, 'index'])
            ->name('assignments.index');
        Route::get('/assignments/{submission}/evaluation', [ReviewerAssignmentController::class, 'evaluation'])
            ->name('assignments.evaluation');
        Route::get('/assignments/{submission}/summary', [ReviewerAssignmentController::class, 'summary'])
            ->name('assignments.summary');
        // BR-REV-16 — tanda Selesai menilai / Selesai memeriksa pada putaran berjalan.
        Route::post('/assignments/{submission}/finish', [ReviewRoundController::class, 'finish'])
            ->name('assignments.finish');
        Route::get('/history', [ReviewerAssignmentController::class, 'history'])
            ->name('history.index');
    });
});

// For backwards compatibility, you can add redirects for admin routes without prefix
Route::middleware(['auth', 'can:users.manage'])->group(function () {
    Route::get('/announcements', function () {
        return redirect()->route('admin.announcements.index');
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

// OPERATOR-07-02 — Export Data (W154). BR-RPT-02/03/10/12 — satu layar untuk Operator dan
// peneliti tanpa permission; yang membedakan mereka SubmissionVisibility, bukan rutenya.
Route::middleware(['auth'])->prefix('reporting')->name('reporting.')->group(function () {
    Route::get('/export', [ExportController::class, 'index'])->name('exports.index');
    Route::post('/export', [ExportController::class, 'store'])->name('exports.store');
    Route::get('/export/{export}/download', [ExportController::class, 'download'])
        ->middleware('signed')
        ->name('exports.download');
    // OPERATOR-07-03 — Audit Log (W155). BR-RPT-05 — satu rute untuk dua pembaca. Yang membedakan
    // Operator dan peneliti adalah cakupan datanya (AuditScope), bukan rutenya: dua rute yang
    // menyaring sendiri-sendiri adalah dua aturan yang akan menyimpang. Tidak ada rute hapus atau
    // sunting — append-only (BR-RPT-04) berarti jalurnya memang tidak dibuat.
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
});

// OPERATOR-07-01 — Dashboard Statistik (W148, W156); tab = route, tab tak dikenal 404.
Route::middleware(['auth', 'can:view-statistics'])->prefix('reporting')->name('reporting.')->group(function () {
    Route::get('/stats/{tab?}', [StatisticsController::class, 'show'])->name('stats');
});

// ADMIN-08-05/06/07 — Form Builder dikelola pemegang schemes.manage (W88).
Route::middleware(['auth', 'can:schemes.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/forms', [FormController::class, 'index'])->name('forms.index');
    Route::get('/forms/create', [FormController::class, 'create'])->name('forms.create');
    Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
    Route::get('/forms/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
    Route::put('/forms/{form}', [FormController::class, 'update'])->name('forms.update');
    Route::patch('/forms/{form}/active', [FormController::class, 'setActive'])->name('forms.active');
    Route::delete('/forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');

    // ADMIN-08-07 — aturan akses bersarang di Form; scopeBindings menolak aturan Form lain (404).
    Route::get('/forms/{form}/access', [FormAccessController::class, 'index'])->name('forms.access.index');
    Route::post('/forms/{form}/access', [FormAccessController::class, 'store'])->name('forms.access.store');
    Route::delete('/forms/{form}/access/{formAccessControl}', [FormAccessController::class, 'destroy'])
        ->scopeBindings()->name('forms.access.destroy');

    // ADMIN-08-08 — pustaka fase (R2): tahap mandiri, `?form=` menapis tahap yang memakai Form itu.
    Route::get('/phases', [FormPhaseController::class, 'index'])->name('phases.index');
    // ADMIN-08-09 — editor tahap mandiri (BR-FE-28: satu Form pengajuan per tahap); mode buat di /create.
    Route::get('/phases/create', [FormPhaseController::class, 'create'])->name('phases.create');
    Route::post('/phases', [FormPhaseController::class, 'store'])->name('phases.store');
    Route::get('/phases/{formPhase}/edit', [FormPhaseController::class, 'edit'])->name('phases.edit');
    Route::put('/phases/{formPhase}', [FormPhaseController::class, 'update'])->name('phases.update');
    Route::patch('/phases/{formPhase}/active', [FormPhaseController::class, 'setActive'])->name('phases.active');
    Route::delete('/phases/{formPhase}', [FormPhaseController::class, 'destroy'])->name('phases.destroy');
    // K9b — menautkan tahap ke periode butuh wewenang periode, selain schemes.manage.
    Route::post('/phases/{formPhase}/periods', [FormPhaseController::class, 'linkPeriod'])
        ->middleware('can:periods.manage')->name('phases.periods.store');

    // ADMIN-08-09 — form evaluasi disimpan per kartu (W95); detailnya dari route, bukan kiriman (W100).
    Route::post('/phase-details/{formPhaseDetail}/evaluation-forms', [EvaluationFormController::class, 'store'])
        ->name('phase-details.evaluation-forms.store');
    Route::put('/evaluation-forms/{reviewEvaluationForm}', [EvaluationFormController::class, 'update'])
        ->name('evaluation-forms.update');
    Route::delete('/evaluation-forms/{reviewEvaluationForm}', [EvaluationFormController::class, 'destroy'])
        ->name('evaluation-forms.destroy');
});

// ADMIN-08-03/04 — skema dikelola pemegang schemes.manage (W105). Nama route tidak berubah.
Route::middleware(['auth', 'can:schemes.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/schemes', [SchemeController::class, 'index'])->name('schemes.index');
    // W106 — buat/sunting skema adalah halaman sendiri (ADMIN-08-04).
    Route::get('/schemes/create', [SchemeController::class, 'create'])->name('schemes.create');
    Route::post('/schemes', [SchemeController::class, 'store'])->name('schemes.store');
    Route::get('/schemes/{scheme}/edit', [SchemeController::class, 'edit'])->name('schemes.edit');
    Route::put('/schemes/{scheme}', [SchemeController::class, 'update'])->name('schemes.update');
    // BR-SCH-01 — penonaktifan diblokir selama skema dipakai pengajuan aktif.
    Route::post('/schemes/{scheme}/deactivate', [SchemeController::class, 'deactivate'])->name('schemes.deactivate');
    Route::post('/schemes/{scheme}/activate', [SchemeController::class, 'activate'])->name('schemes.activate');
});

// ADMIN-08-02 — struktur organisasi dikelola pemegang organizations.manage (W105, pemilik 2026-11-07).
Route::middleware(['auth', 'can:organizations.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    // BR-IAM-17 — menyusun pohon organisasi.
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    // BR-IAM-18 — memindahkan orangnya, prasyarat yang diminta BR-IAM-14.
    Route::post('/organizations/{organization}/move-user', [OrganizationController::class, 'moveUser'])->name('organizations.move-user');
    // BR-IAM-14 — ambangnya user aktif; BR-IAM-08 — penghapusan, undangan yang belum
    // terpakai ikut tercabut alih-alih menghalangi (register #18).
    Route::post('/organizations/{organization}/deactivate', [OrganizationController::class, 'deactivate'])->name('organizations.deactivate');
    // BR-IAM-22 — mengaktifkan kembali satu unit, ditolak selama induknya nonaktif.
    Route::post('/organizations/{organization}/reactivate', [OrganizationController::class, 'reactivate'])->name('organizations.reactivate');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
});

// OPERATOR-09-01 — periode dikelola pemegang periods.manage, bukan users.manage (§4.4 K1).
Route::middleware(['auth', 'can:periods.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/submission-periods', [SubmissionPeriodController::class, 'index'])
        ->name('submission-periods.index');
    Route::post('/submission-periods', [SubmissionPeriodController::class, 'store'])
        ->name('submission-periods.store');
    Route::put('/submission-periods/{submissionPeriod}', [SubmissionPeriodController::class, 'update'])
        ->name('submission-periods.update');
    Route::delete('/submission-periods/{submissionPeriod}', [SubmissionPeriodController::class, 'destroy'])
        ->name('submission-periods.destroy');
    Route::post('/submission-periods/{submissionPeriod}/close', [SubmissionPeriodController::class, 'close'])
        ->name('submission-periods.close');
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
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        // BR-REV-05, ADR 0018 — Setujui oleh Operator, sebelah Tolak.
        Route::post('/submissions/{submission}/approve', [SubmissionDecisionController::class, 'approve'])
            ->name('submissions.approve');
        Route::post('/submissions/{submission}/reject', [SubmissionDecisionController::class, 'reject'])
            ->name('submissions.reject');
        Route::post('/submissions/{submission}/transfer-ownership', [SubmissionDecisionController::class, 'transferOwnership'])
            ->name('submissions.transfer-ownership');
        Route::post('/submissions/{submission}/reviewers/{reviewer}/replace', ReplaceReviewerController::class)
            ->name('submissions.replace-reviewer');
        // BR-REV-01 — dijaga Policy `assignReviewers` (reviewers.assign), bukan users.manage.
        Route::post('/submissions/{submission}/assign-reviewers', [ReviewController::class, 'assignReviewers'])
            ->name('submissions.assign-reviewers');
        // BR-FE-08/09/10 — perpanjangan akses satu fase untuk satu pengajuan.
        Route::post('/submissions/{submission}/overrides', [PhaseOverrideController::class, 'store'])
            ->name('submissions.overrides.store');
        Route::delete('/submissions/{submission}/overrides/{override}', [PhaseOverrideController::class, 'destroy'])
            ->name('submissions.overrides.destroy');
    });

    Route::post('/review-summaries/{reviewSummary}/resolve', ReviewSummaryResolutionController::class)
        ->name('review-summaries.resolve');

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

    // BR-RPT-11 — cetak memakai penyimpanan, retensi, dan unduhan yang sama
    // dengan ekspor; yang berbeda hanya penulisnya.
    Route::post('/submissions/{submission}/print/{document}', DocumentPrintController::class)
        ->name('submissions.print');
});

// BR-SC-01/02/03 — Konfigurasi Umum. Satu himpunan rute untuk semua data
// acuan: yang membedakan mereka adalah entri registry, bukan controller
// masing-masing, sehingga aturan hapus dan nonaktif tertulis sekali.
// ADMIN-08-10 — Konfigurasi Umum dikelola pemegang schemes.manage (W105, sidebar.md).
Route::middleware(['auth', 'can:schemes.manage'])->prefix('admin/reference')->name('admin.reference.')->group(function () {
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

// W163 — URL tak dikenal melewati middleware `web` supaya halaman 404 tahu siapa yang
// masuk (shell aplikasi vs. halaman tamu).
Route::fallback(fn () => abort(404));
