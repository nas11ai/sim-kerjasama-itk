<?php

// BR-REV-19 — Operator menerapkan versi terbaru Form Evaluasi ke satu angkatan × langkah. Jawaban
// yang ada menjadi riwayat, reviewer-nya mengisi ulang dan diberi tahu (evaluation_version.feature).

use App\Actions\Review\ApplyLatestEvaluationVersion;
use App\Actions\Review\ApproveSubmission;
use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\PublishEvaluationFormVersion;
use App\Actions\Review\SaveEvaluationDraft;
use App\Actions\Review\SubmitEvaluationAnswers;
use App\Data\Request\EvaluationAnswersInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Enums\ReviewRoundKind;
use App\Models\EvaluationFormPin;
use App\Models\FieldType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use App\Models\ReviewRound;
use App\Models\StepEvaluationForm;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\EvaluationVersionAppliedNotification;
use App\Queries\Review\DecisionReadiness;
use App\Queries\Review\SubmittedEvaluationSheets;
use App\Queries\Review\VisibleReviewerScores;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->phase = FormPhase::create(['title' => 'Penelitian DIPA', 'is_active' => true]);
    $this->step = scheduleStep($this->phase);
    $this->p2025 = schedulePeriod($this->phase, '2025');
    $this->p2026 = schedulePeriod($this->phase, '2026');
    $this->form = evaluationFormOn($this->step, [['type' => 'Text', 'label' => 'Kebaruan', 'required' => true]], title: 'Lembar Proposal');
    $this->operator = seededRole('operator');
});

function applySubmission(FormPhaseDetail $step, SubmissionPeriod $period, string $status = UnderReview::class): FormSubmission
{
    return scheduledSubmission($step, $period, ['status' => $status, 'is_submitted' => true]);
}

function applySeat(FormSubmission $submission, ReviewEvaluationForm $form): ReviewerFormAssignment
{
    return AssignEvaluationForm::run(reviewerOn($submission, 'pending'), $form->id);
}

/** Jawaban satu-satunya field versi yang berlaku bagi penugasan itu. */
function applyAnswers(ReviewerFormAssignment $assignment, string $value): EvaluationAnswersInput
{
    $field = $assignment->reviewEvaluationForm->latestVersion()->firstOrFail()->reviewFormFields()->firstOrFail();

    return new EvaluationAnswersInput([$field->id => $value], null);
}

function applySubmitted(ReviewerFormAssignment $assignment, string $value = 'Baru sekali'): ReviewerFormAssignment
{
    // Muat ulang jawaban berlakunya: yang ter-cache bisa sudah menjadi riwayat.
    $assignment->refresh();
    SubmitEvaluationAnswers::run($assignment, applyAnswers($assignment, $value), $assignment->submissionReviewer->reviewer->user);

    return $assignment->refresh();
}

function applyDrafted(ReviewerFormAssignment $assignment): ReviewerFormAssignment
{
    SaveEvaluationDraft::run($assignment, applyAnswers($assignment, 'Setengah jadi'), $assignment->submissionReviewer->reviewer->user);

    return $assignment->refresh();
}

function applyVersion2(ReviewEvaluationForm $form): ReviewEvaluationFormVersion
{
    $text = FieldType::firstOrCreate(['name' => 'Text'])->id;

    return PublishEvaluationFormVersion::run($form, [new ReviewFormFieldInput(null, $text, 'Orisinalitas', null, true, null, null)], User::factory()->create());
}

function cohortPin(SubmissionPeriod $period, FormPhaseDetail $step, ReviewEvaluationForm $form): EvaluationFormPin
{
    return EvaluationFormPin::query()
        ->where(['submission_period_id' => $period->id, 'form_phase_detail_id' => $step->id, 'review_evaluation_form_id' => $form->id])
        ->sole();
}

it('[REV-VER-06] reopens submitted evaluations on the new version and keeps the old answers as history', function () {
    $submission = applySubmission($this->step, $this->p2025);
    $sari = applySubmitted(applySeat($submission, $this->form));
    $v2 = applyVersion2($this->form);
    $pin = cohortPin($this->p2025, $this->step, $this->form);

    $reopened = ApplyLatestEvaluationVersion::run($pin, $this->operator);

    expect($reopened)->toBe(1)
        ->and($pin->refresh()->review_evaluation_form_version_id)->toBe($v2->id)
        ->and($sari->refresh()->reviewFormResponse)->toBeNull()
        ->and($sari->supersededResponses()->sole()->status)->toBe('submitted')
        ->and($sari->submissionReviewer->refresh()->evaluation_status)->toBe('pending')
        ->and(Activity::query()->where('description', 'Versi baru form evaluasi diterapkan')->sole()->properties['new'])
        ->toMatchArray(['version' => 2, 'reopened' => 1]);

    $sheets = SubmittedEvaluationSheets::run($submission, true);
    expect($sheets)->toHaveCount(1)
        ->and($sheets[0])->toMatchArray(['version_label' => 'Versi 1', 'superseded' => true])
        ->and($sheets[0]['answers'])->toBe([['label' => 'Kebaruan', 'value' => 'Baru sekali']]);

    // Reviewer mengisi ulang pada versi baru; lembar barunya berlaku, yang lama tetap riwayat.
    applySubmitted($sari, 'Orisinal');
    expect(array_map(fn (array $s): array => [$s['version_label'], $s['superseded']], SubmittedEvaluationSheets::run($submission, true)))
        ->toBe([['Versi 1', true], ['Versi 2', false]]);
});

it('[REV-VER-07] refuses when a submission at that step is already decided', function () {
    Notification::fake();
    $sari = applySubmitted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    applySubmission($this->step, $this->p2025, NeedsRevision::class);
    $v1 = $this->form->latestVersion()->value('id');
    applyVersion2($this->form);
    $pin = cohortPin($this->p2025, $this->step, $this->form);

    expect(fn () => ApplyLatestEvaluationVersion::run($pin, $this->operator))
        ->toThrow(ValidationException::class, 'Tidak bisa diterapkan: 1 pengajuan di langkah ini sudah diputuskan.');

    expect($pin->refresh()->review_evaluation_form_version_id)->toBe($v1)
        ->and($sari->refresh()->reviewFormResponse?->status)->toBe('submitted')
        ->and($sari->submissionReviewer->refresh()->evaluation_status)->toBe('completed')
        ->and(ReviewRound::query()->where('form_submission_id', $sari->submissionReviewer->form_submission_id)->sole()->participants()->sole()->finished_at)->toBeNull();
    Notification::assertNothingSent();
});

it('[REV-VER-08] notifies every reviewer whose evaluation was reopened', function () {
    Notification::fake();
    $sari = applySubmitted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    $dodi = applyDrafted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    $untouched = applySeat(applySubmission($this->step, $this->p2025), $this->form);
    applyVersion2($this->form);

    expect(ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator))->toBe(2);

    foreach ([$sari, $dodi] as $assignment) {
        Notification::assertSentTo($assignment->submissionReviewer->reviewer->user, EvaluationVersionAppliedNotification::class,
            fn (EvaluationVersionAppliedNotification $n, array $channels, User $user) => $n->toArray($user)['message']
                === "Lembar \"Lembar Proposal\" untuk pengajuan #{$assignment->submissionReviewer->form_submission_id} diganti ke Versi 2. Isi ulang penilaian Anda; jawaban lama tetap tersimpan sebagai riwayat.");
    }
    Notification::assertNotSentTo($untouched->submissionReviewer->reviewer->user, EvaluationVersionAppliedNotification::class);
    Notification::assertCount(2);
});

it('[REV-VER-09] leaves the other cohort of the same phase untouched', function () {
    applySubmitted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    $dodi = applySubmitted(applySeat(applySubmission($this->step, $this->p2026), $this->form));
    $v1 = $this->form->latestVersion()->value('id');
    applyVersion2($this->form);

    ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator);

    expect(cohortPin($this->p2026, $this->step, $this->form)->review_evaluation_form_version_id)->toBe($v1)
        ->and($dodi->refresh()->reviewFormResponse?->status)->toBe('submitted')
        ->and($dodi->supersededResponses()->count())->toBe(0);
});

it('refuses when the cohort already uses the latest version', function () {
    Notification::fake();
    applySubmitted(applySeat(applySubmission($this->step, $this->p2025), $this->form));

    expect(fn () => ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator))
        ->toThrow(ValidationException::class, 'Angkatan ini sudah memakai versi terbaru.');
    Notification::assertNothingSent();
});

it('counts decided monev reports at a monev step', function () {
    $monev1 = scheduleStep($this->phase, cycle: 1, order: 2);
    StepEvaluationForm::create(['form_phase_detail_id' => $monev1->id, 'review_evaluation_form_id' => $this->form->id, 'order' => 1]);
    // Satu laporan per induk per langkah (one_child_submission_per_phase_detail).
    $report = fn (): FormSubmission => tap(applySubmission($monev1, $this->p2025), fn (FormSubmission $r) => $r->update([
        'parent_submission_id' => applySubmission($this->step, $this->p2025, Approved::class)->id,
    ]));
    applySubmitted(applySeat($report(), $this->form));
    applyDrafted(applySeat($report(), $this->form));
    // Laporan yang belum punya reviewer belum dinilai, jadi belum diputuskan.
    $report();
    applyVersion2($this->form);

    expect(fn () => ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $monev1, $this->form), $this->operator))
        ->toThrow(ValidationException::class, 'Tidak bisa diterapkan: 1 pengajuan di langkah ini sudah diputuskan.');
});

it('reopens a round that finished ready, so the operator cannot approve until the reviewer finishes again', function () {
    $submission = applySubmission($this->step, $this->p2025);
    $sari = applySubmitted(applySeat($submission, $this->form));
    finishSeat($sari->submissionReviewer);
    expect(DecisionReadiness::run($submission->refresh())->ready)->toBeTrue();
    applyVersion2($this->form);

    ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator);

    $round = $submission->latestReviewRound();
    expect($round?->completed_at)->toBeNull()
        ->and($round?->outcome)->toBeNull()
        ->and($round?->participants()->sole()->finished_at)->toBeNull()
        ->and($submission->refresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and(DecisionReadiness::run($submission)->ready)->toBeFalse()
        ->and(fn () => ApproveSubmission::run($submission, $this->operator))->toThrow(AuthorizationException::class);

    applySubmitted($sari, 'Orisinal');
    finishSeat($sari->submissionReviewer);
    expect(DecisionReadiness::run($submission->refresh())->ready)->toBeTrue();
});

it('counts a withdrawn submission as decided', function () {
    applySubmitted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    applySubmission($this->step, $this->p2025, Withdrawn::class);
    applyVersion2($this->form);

    expect(fn () => ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator))
        ->toThrow(ValidationException::class, 'Tidak bisa diterapkan: 1 pengajuan di langkah ini sudah diputuskan.');
});

it('writes a draft saved through an assignment loaded before the apply onto the new version', function () {
    $sari = applyDrafted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    $stale = ReviewerFormAssignment::query()->with('reviewFormResponse')->findOrFail($sari->id);
    $v2 = applyVersion2($this->form);
    ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator);

    SaveEvaluationDraft::run($stale, applyAnswers($stale, 'Orisinal'), $stale->submissionReviewer->reviewer->user);

    $history = $sari->supersededResponses()->sole();
    expect($sari->refresh()->reviewFormResponse?->review_evaluation_form_version_id)->toBe($v2->id)
        ->and($sari->reviewFormResponse?->reviewFormFieldResponses()->sole()->value)->toBe('Orisinal')
        ->and($history->reviewFormFieldResponses()->sole()->value)->toBe('Setengah jadi');
});

/** Kursi yang Selesai di putaran 1 (`ready`), lalu reviewer kedua ditugaskan: putaran 2 hanya berisi kursi baru. */
function lateSecondReviewer(FormSubmission $submission, ReviewEvaluationForm $form): array
{
    $sari = applySubmitted(applySeat($submission, $form));
    finishSeat($sari->submissionReviewer);
    $budi = applySeat($submission, $form);

    return [$sari->submissionReviewer, $budi];
}

it('puts a reopened seat back into a later round it was never part of', function () {
    $submission = applySubmission($this->step, $this->p2025);
    [$sari] = lateSecondReviewer($submission, $this->form);
    expect($submission->latestReviewRound()?->number)->toBe(2);
    applyVersion2($this->form);

    ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator);

    $round = $submission->latestReviewRound();
    expect($round?->unfinishedParticipants()->where('submission_reviewer_id', $sari->id)->sole()->kind)->toBe(ReviewRoundKind::Assessment)
        ->and(DecisionReadiness::run($submission->refresh())->ready)->toBeFalse();
});

it('reopens a later round that already finished ready and seats the reopened reviewer in it', function () {
    $submission = applySubmission($this->step, $this->p2025);
    [$sari, $budi] = lateSecondReviewer($submission, $this->form);
    applySubmitted($budi);
    finishSeat($budi->submissionReviewer);
    expect($submission->latestReviewRound()?->outcome)->toBe(ReviewRound::OUTCOME_READY);
    applyVersion2($this->form);

    ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator);

    $round = $submission->latestReviewRound();
    expect($round?->number)->toBe(2)
        ->and($round?->completed_at)->toBeNull()
        ->and($round?->unfinishedParticipants()->pluck('submission_reviewer_id')->sort()->values()->all())
        ->toBe(collect([$sari->id, $budi->submission_reviewer_id])->sort()->values()->all());
});

it('hides superseded answers from the researcher but shows them to the operator', function () {
    $submission = applySubmission($this->step, $this->p2025);
    $sari = applySubmitted(applySeat($submission, $this->form));
    applyVersion2($this->form);
    ApplyLatestEvaluationVersion::run(cohortPin($this->p2025, $this->step, $this->form), $this->operator);
    applySubmitted($sari, 'Orisinal');
    $submission->status->transitionTo(Approved::class);

    $researcher = VisibleReviewerScores::run($submission->submittedBy, $submission->refresh());
    expect($researcher['visible'])->toBeTrue()
        ->and(array_column($researcher['scores'], 'version_label'))->toBe(['Versi 2'])
        ->and(array_column(SubmittedEvaluationSheets::run($submission, true), 'version_label'))->toBe(['Versi 1', 'Versi 2']);
});

it('lists each reviewed step of the period with its pinned and latest version', function () {
    $proposal = applySubmission($this->step, $this->p2025);
    applySubmitted(applySeat($proposal, $this->form));
    applyDrafted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    applyVersion2($this->form);
    $monev1 = scheduleStep($this->phase, cycle: 1, order: 2);
    StepEvaluationForm::create(['form_phase_detail_id' => $monev1->id, 'review_evaluation_form_id' => $this->form->id, 'order' => 1]);
    $pin = cohortPin($this->p2025, $this->step, $this->form);

    $this->actingAs($this->operator)
        ->get(route('admin.submission-periods.evaluation-versions', $this->p2025))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FormEngine/PeriodEvaluationVersionsPage')
            ->where('period', ['id' => $this->p2025->id, 'name' => '2025'])
            ->has('rows', 2)
            ->where('rows.0', [
                'phaseTitle' => 'Penelitian DIPA', 'stepLabel' => 'Pengajuan', 'formTitle' => 'Lembar Proposal',
                'pinId' => $pin->id, 'pinnedLabel' => 'Versi 1', 'latestLabel' => 'Versi 2',
                'responseCount' => 2, 'decidedCount' => 0, 'canApply' => true,
            ])
            ->where('rows.1.pinId', null)
            ->where('rows.1.pinnedLabel', null)
            ->where('rows.1.canApply', false));
});

it('applies from the period screen and reports how many evaluations were reopened', function () {
    applySubmitted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    applyVersion2($this->form);
    $pin = cohortPin($this->p2025, $this->step, $this->form);

    $this->actingAs($this->operator)
        ->from(route('admin.submission-periods.evaluation-versions', $this->p2025))
        ->post(route('admin.evaluation-form-pins.apply', $pin))
        ->assertRedirect(route('admin.submission-periods.evaluation-versions', $this->p2025))
        ->assertSessionHas('success', 'Versi 2 diterapkan; 1 penilaian dibuka ulang.');
});

it('forbids the page and the action without periods.manage', function () {
    applySubmitted(applySeat(applySubmission($this->step, $this->p2025), $this->form));
    applyVersion2($this->form);
    $pin = cohortPin($this->p2025, $this->step, $this->form);
    $stranger = verifiedResearcher();

    $this->actingAs($stranger)->get(route('admin.submission-periods.evaluation-versions', $this->p2025))->assertForbidden();
    $this->actingAs($stranger)->post(route('admin.evaluation-form-pins.apply', $pin))->assertForbidden();
    expect($pin->refresh()->review_evaluation_form_version_id)->not->toBe($this->form->latestVersion()->value('id'));
});
