<?php

// tests/Feature/Review/ReviewThreadCreationTest.php
//
// BR-FE-06 lewat jalur HTTP yang sebenarnya. ReviewSummaryGateTest menguji
// guard-nya langsung; berkas ini membuktikan endpoint-nya memang memanggil
// guard itu, bukan definisi "evaluasi selesai" karangannya sendiri.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/** @return array{0: User, 1: FormSubmission} */
function threadFixture(string $evaluationStatus, bool $formWajib): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'submitted_by' => User::factory()->create()->id,
        'status' => UnderReview::class,
    ]);

    $user = User::factory()->create();
    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluationStatus,
        'status' => 'active',
    ]);

    // Fase ini selalu punya form evaluasi; yang berbeda hanya wajib-tidaknya.
    ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => $formWajib,
        'order' => 1,
    ]);

    return [$user, $submission];
}

it('[REV-EVAL-04] menolak ulir saat evaluasi belum selesai meski tak ada form wajib', function () {
    // Tidak ada form wajib, jadi hitungan "form wajib yang tersubmit" bilang
    // beres. BR-FE-06 menggerbang `evaluation_status`, dan ia masih pending.
    [$user, $submission] = threadFixture('pending', formWajib: false);

    $this->actingAs($user)
        ->post(route('reviewer.review-threads.store', $submission), [
            'summary_notes' => 'Catatan awal.',
        ])
        ->assertSessionHasErrors('review_summary');

    expect(ReviewSummary::count())->toBe(0);
});

it('[REV-EVAL-06] mengizinkan ulir bagi reviewer not_required walau form wajib menganggur', function () {
    // `not_required` tidak pernah punya jawaban tersubmit. Digerbang pada
    // jawaban, reviewer ini tidak akan pernah bisa membuka ulir.
    [$user, $submission] = threadFixture('not_required', formWajib: true);

    $this->actingAs($user)
        ->post(route('reviewer.review-threads.store', $submission), [
            'summary_notes' => 'Catatan tanpa kewajiban menilai.',
        ])
        ->assertSessionHasNoErrors();

    expect(ReviewSummary::count())->toBe(1);
});

it('[REV-EVAL-05] mengizinkan ulir setelah evaluasi selesai', function () {
    [$user, $submission] = threadFixture('completed', formWajib: false);

    $this->actingAs($user)
        ->post(route('reviewer.review-threads.store', $submission), [
            'summary_notes' => 'Catatan sesudah selesai.',
        ])
        ->assertSessionHasNoErrors();

    expect(ReviewSummary::count())->toBe(1);
});

// BR-REV-06 dan BR-REV-15 — ulir open menahan persetujuan dan hanya bisa ditutup
// reviewer. Ulir tanpa reviewer tak punya pihak yang berhak menutupnya.
it('menolak Operator tanpa penugasan membuka ulir', function () {
    [, $submission] = threadFixture('completed', false);

    Permission::findOrCreate('users.manage', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $this->actingAs($operator)
        ->post(route('admin.review-threads.store', $submission), ['summary_notes' => 'Catatan operator.'])
        ->assertForbidden();

    expect(ReviewSummary::count())->toBe(0);
});

it('tidak menawarkan tombol membuka ulir kepada Operator', function () {
    [, $submission] = threadFixture('completed', false);
    $submission->update(['is_submitted' => true]);

    Permission::findOrCreate('users.manage', 'web');
    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(['users.manage', 'submissions.view-all']);

    $this->actingAs($operator)
        ->get(route('admin.submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('canCreateThread', false));
});

it('[BR-SM-07] lampiran review tersimpan di disk privat dan tetap bisa diunduh pihak yang berhak', function () {
    Storage::fake('local');
    Storage::fake('public');
    [$user, $submission] = threadFixture('not_required', formWajib: true);

    $this->actingAs($user)
        ->post(route('reviewer.review-threads.store', $submission), [
            'summary_notes' => 'Catatan berlampiran.',
            'attachments' => [UploadedFile::fake()->create('catatan.pdf', 10)],
        ])
        ->assertSessionHasNoErrors();

    $path = ReviewSummary::where('form_submission_id', $submission->id)->sole()->attachments()->value('file_path');

    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);

    $this->actingAs($submission->submittedBy)
        ->get(route('review-attachments.download', ['path' => $path]))
        ->assertOk();
});
