<?php

// BR-REV-18 — versi Form Evaluasi dipaku per angkatan × langkah × Form Evaluasi saat draf
// penilaian pertama di sana tersimpan (evaluation_version.feature).

use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\PinEvaluationVersion;
use App\Actions\Review\PublishEvaluationFormVersion;
use App\Actions\Review\SaveEvaluationDraft;
use App\Data\Request\EvaluationAnswersInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Data\View\Submission\Wizard\WizardFieldData;
use App\Models\EvaluationFormPin;
use App\Models\FieldType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use App\Models\StepEvaluationForm;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Review\ReviewerEvaluation;
use App\Queries\Review\SubmittedEvaluationSheets;
use App\States\Submission\UnderReview;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->phase = FormPhase::create(['title' => 'Penelitian DIPA', 'is_active' => true]);
    $this->step = scheduleStep($this->phase);
    $this->p2025 = schedulePeriod($this->phase, '2025');
    $this->p2026 = schedulePeriod($this->phase, '2026');
    $this->form = evaluationFormOn($this->step, [['type' => 'Text', 'label' => 'Kebaruan']], title: 'Lembar Proposal');
    $this->admin = User::factory()->create();
});

function versionSubmission(FormPhaseDetail $step, SubmissionPeriod $period): FormSubmission
{
    return scheduledSubmission($step, $period, ['status' => UnderReview::class, 'is_submitted' => true]);
}

function versionSeat(FormSubmission $submission, ReviewEvaluationForm $form): ReviewerFormAssignment
{
    return AssignEvaluationForm::run(reviewerOn($submission, 'pending'), $form->id);
}

/** Simpan draf berisi `$answers` (id field => nilai) — "mulai mengisi". */
function saveDraft(ReviewerFormAssignment $assignment, array $answers = []): void
{
    SaveEvaluationDraft::run($assignment, new EvaluationAnswersInput($answers, null), $assignment->submissionReviewer->reviewer->user);
}

/** @return list<string> label field yang dilihat reviewer pada form pertama layarnya */
function labelsSeenBy(ReviewerFormAssignment $assignment): array
{
    $forms = ReviewerEvaluation::run($assignment->submissionReviewer->refresh(), null)['forms'];

    return array_map(fn (WizardFieldData $field): string => $field->label, $forms[0]->fields);
}

function publishRelabel(ReviewEvaluationForm $form, string $label, User $admin): ReviewEvaluationFormVersion
{
    $text = FieldType::firstOrCreate(['name' => 'Text'])->id;

    return PublishEvaluationFormVersion::run($form, [new ReviewFormFieldInput(null, $text, $label, null, false, null, null)], $admin);
}

function pinOf(SubmissionPeriod $period, FormPhaseDetail $step, ReviewEvaluationForm $form): ?int
{
    return EvaluationFormPin::query()
        ->where(['submission_period_id' => $period->id, 'form_phase_detail_id' => $step->id, 'review_evaluation_form_id' => $form->id])
        ->value('review_evaluation_form_version_id');
}

it('[REV-VER-01] shows the latest published version before the first draft', function () {
    $v2 = publishRelabel($this->form, 'Orisinalitas', $this->admin);
    $seat = versionSeat(versionSubmission($this->step, $this->p2025), $this->form);

    expect(labelsSeenBy($seat))->toBe(['Orisinalitas'])
        ->and($v2->label())->toBe('Versi 2')
        ->and(EvaluationFormPin::query()->count())->toBe(0);
});

it('[REV-VER-02] gives a reviewer assigned later the pinned version', function () {
    $sari = versionSeat(versionSubmission($this->step, $this->p2025), $this->form);
    saveDraft($sari);
    publishRelabel($this->form, 'Orisinalitas', $this->admin);

    $dodi = versionSeat(versionSubmission($this->step, $this->p2025), $this->form);
    expect(labelsSeenBy($dodi))->toBe(['Kebaruan']);

    saveDraft($dodi);
    expect($dodi->refresh()->reviewFormResponse->reviewEvaluationFormVersion->number)->toBe(1);
});

it('[REV-VER-03] pins per step: the Monev I sheet keeps following the latest version', function () {
    $monev1 = scheduleStep($this->phase, cycle: 1, order: 2);
    StepEvaluationForm::create(['form_phase_detail_id' => $monev1->id, 'review_evaluation_form_id' => $this->form->id, 'order' => 1]);
    $proposal = versionSubmission($this->step, $this->p2025);
    saveDraft(versionSeat($proposal, $this->form));
    publishRelabel($this->form, 'Orisinalitas', $this->admin);

    $report = versionSubmission($monev1, $this->p2025);
    $report->update(['parent_submission_id' => $proposal->id]);
    $monevSeat = versionSeat($report, $this->form);

    expect(labelsSeenBy($monevSeat))->toBe(['Orisinalitas']);
    saveDraft($monevSeat);
    expect(pinOf($this->p2025, $monev1, $this->form))->toBe($this->form->latestVersion()->value('id'))
        ->and(pinOf($this->p2025, $this->step, $this->form))->toBe($this->form->versions()->where('number', 1)->value('id'));
});

it('[REV-VER-04] lets another cohort of the same phase use the latest version', function () {
    saveDraft(versionSeat(versionSubmission($this->step, $this->p2025), $this->form));
    $v2 = publishRelabel($this->form, 'Orisinalitas', $this->admin);

    saveDraft(versionSeat(versionSubmission($this->step, $this->p2026), $this->form));

    expect(pinOf($this->p2026, $this->step, $this->form))->toBe($v2->id)
        ->and(pinOf($this->p2025, $this->step, $this->form))->toBe($this->form->versions()->where('number', 1)->value('id'));
});

it('[REV-VER-05] keeps the old version fields when a new version is published', function () {
    $submission = versionSubmission($this->step, $this->p2025);
    $seat = versionSeat($submission, $this->form);
    $kebaruan = $this->form->versions()->sole()->reviewFormFields()->sole();
    saveDraft($seat, [$kebaruan->id => 'Baru sekali']);
    $seat->refresh()->reviewFormResponse->update(['status' => 'submitted', 'submitted_at' => now()]);

    publishRelabel($this->form, 'Orisinalitas', $this->admin);

    $sheets = SubmittedEvaluationSheets::run($submission, false);
    expect($sheets[0]['answers'])->toBe([['label' => 'Kebaruan', 'value' => 'Baru sekali']])
        ->and(fn () => $kebaruan->update(['label' => 'Orisinalitas']))->toThrow(LogicException::class);
});

it('pins once when two reviewers save their first draft', function () {
    $first = versionSubmission($this->step, $this->p2025);
    $second = versionSubmission($this->step, $this->p2025);

    $a = PinEvaluationVersion::run($first, $this->form);
    publishRelabel($this->form, 'Orisinalitas', $this->admin);
    $b = PinEvaluationVersion::run($second, $this->form);

    expect($b->id)->toBe($a->id)
        ->and(EvaluationFormPin::query()->count())->toBe(1);
});

it('refuses to update a published version field', function () {
    $field = $this->form->versions()->sole()->reviewFormFields()->sole();

    expect(fn () => $field->update(['label' => 'Lain']))->toThrow(LogicException::class)
        ->and(fn () => $this->form->versions()->sole()->update(['number' => 9]))->toThrow(LogicException::class);
});

it('rejects answers for fields of another version', function () {
    $seat = versionSeat(versionSubmission($this->step, $this->p2025), $this->form);
    saveDraft($seat);
    $v2Field = publishRelabel($this->form, 'Orisinalitas', $this->admin)->reviewFormFields()->sole();

    try {
        saveDraft($seat, [$v2Field->id => 'Salah versi']);
        $this->fail('Jawaban untuk field versi lain harus ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('responses');
    }
});

it('numbers the published version and records it in the audit trail', function () {
    $v2 = publishRelabel($this->form, 'Orisinalitas', $this->admin);

    $audit = Activity::query()->where('description', 'Form evaluasi diterbitkan')->where('subject_id', $this->form->id)->sole();
    expect($v2->number)->toBe(2)
        ->and($v2->published_by)->toBe($this->admin->id)
        ->and($audit->properties['new'])->toMatchArray(['number' => 2, 'fields' => ['Orisinalitas']]);
});
