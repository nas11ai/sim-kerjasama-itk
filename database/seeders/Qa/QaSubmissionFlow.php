<?php

namespace Database\Seeders\Qa;

use App\Actions\Budget\SaveBudgetLineItem;
use App\Actions\FormSubmission\AddResearchMember;
use App\Actions\FormSubmission\AddStudentMember;
use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Actions\FormSubmission\SaveTargetOutput;
use App\Actions\FormSubmission\SaveWizardStep;
use App\Actions\FormSubmission\StartSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Actions\FormSubmission\WithdrawSubmission;
use App\Actions\Monev\AssignCycleReviewer;
use App\Actions\Monev\OpenMonevCycle;
use App\Actions\Monev\SaveMonevReport;
use App\Actions\Monev\SubmitMonevReport;
use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Actions\Review\ApproveSubmission;
use App\Actions\Review\AssignReviewers;
use App\Actions\Review\FinishReviewRound;
use App\Actions\Review\OpenEvaluationResponse;
use App\Actions\Review\OpenReviewThread;
use App\Actions\Review\RejectSubmission;
use App\Actions\Review\StartEvaluationForm;
use App\Actions\Review\SubmitEvaluationAnswers;
use App\Data\Request\EvaluationAnswersInput;
use App\Data\Request\MonevReportInput;
use App\Data\Request\ResearchOutputInput;
use App\Data\Request\SubmissionAnswersInput;
use App\Data\Request\WizardStepInput;
use App\Models\BudgetComponent;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\ReviewFormField;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Langkah alur pengajuan yang dipakai ulang seeder QA. Setiap metode memanggil Action yang sama
 * dengan tombol di layar, dengan pelaku yang sama: status hanya berpindah lewat `transitionTo()`
 * di dalam Action itu.
 */
final class QaSubmissionFlow
{
    public function __construct(public readonly QaCatalogResult $catalog) {}

    /**
     * Langkah 1 wizard (StartSubmission) lalu langkah 2..`$untilStep` (SaveWizardStep). Langkah 2 mengisi
     * informasi dasar, langkah 3 skema dan TKT, langkah 6 berkas proposal; langkah 4–5 (anggota,
     * anggaran) diisi lewat `team()`. `last_completed_step` berakhir di `$untilStep` (paling tinggi 6:
     * langkah 7 adalah Kirim).
     */
    public function draft(User $lead, string $scheme, string $title, string $period = 'current', int $untilStep = 6): FormSubmission
    {
        $formKey = QaCatalogResult::formKeyOf($scheme);
        $form = $this->catalog->forms[$formKey];
        $periodId = $this->catalog->periods[$period]->id;

        $submission = StartSubmission::run($form->id, $periodId, $this->catalog->steps[$formKey]['submit'], $lead);

        foreach (range(2, $untilStep) as $step) {
            [$answers, $uploads] = $this->answersFor($step, $formKey, $scheme, $title);
            $submission = SaveWizardStep::run($submission, new WizardStepInput(
                new SubmissionAnswersInput($form->id, $periodId, $answers, $uploads, $submission->fresh()?->updated_at?->toIso8601String()),
                $step,
                $step + 1,
            ), $lead);
        }

        return $submission->refresh();
    }

    /** Langkah 4–5: anggota dosen dan mahasiswa, dua baris anggaran dalam pagu skema, satu target luaran. */
    public function team(FormSubmission $submission, User $lead, User $member): void
    {
        AddResearchMember::run($submission, $member, ResearchMember::ROLE_MEMBER);
        AddStudentMember::run($submission, $lead, [
            'name' => fake('id_ID')->name(),
            'student_id' => (string) fake('id_ID')->unique()->numerify('1120#####'),
            'study_program' => $lead->userProfile?->organization?->name,
        ]);

        $components = BudgetComponent::query()->where('is_active', true)->orderBy('id')->limit(2)->get();
        foreach ($components as $i => $component) {
            SaveBudgetLineItem::run($submission->refresh(), $lead, [
                'budget_component_id' => $component->id,
                'item_name' => $i === 0 ? 'Bahan uji laboratorium' : 'Perjalanan pengambilan data',
                'volume' => $i === 0 ? 10 : 4,
                'unit' => $i === 0 ? 'paket' : 'kali',
                'unit_price' => $i === 0 ? 1_500_000 : 2_000_000,
            ]);
        }

        SaveTargetOutput::run($submission->refresh(), $lead, 'article');
    }

    public function submit(FormSubmission $submission, User $lead): FormSubmission
    {
        return SubmitFormSubmission::run($submission->refresh(), $lead);
    }

    /** BR-REV-01 — `min_reviewer_count` skema (2) tercapai memindahkan pengajuan ke Sedang Dinilai. */
    public function assign(FormSubmission $submission, Reviewer ...$reviewers): void
    {
        AssignReviewers::run($submission->refresh(), array_map(fn (Reviewer $r): int => $r->id, $reviewers), true);
    }

    /**
     * Penilai membuka setiap lembar penilaian aktif langkah ini (StartEvaluationForm, sama dengan layar;
     * mengembalikan penugasan yang sudah ada) lalu mengirim jawaban akhirnya. Kursi Monev tidak diberi
     * lembar saat ditugaskan, jadi jalur "buka lembar" inilah yang membuatnya.
     */
    public function evaluate(FormSubmission $submission, Reviewer $reviewer, int $score = 80): void
    {
        $user = $reviewer->user()->sole();
        $steps = $submission->refresh()->formPhaseDetail()->sole()->stepEvaluationForms()->where('is_active', true)->with('reviewEvaluationForm')->get();

        foreach ($steps as $step) {
            $assignment = StartEvaluationForm::run($submission, $step->reviewEvaluationForm()->sole(), $user);
            $version = OpenEvaluationResponse::run($assignment, $user)->reviewEvaluationFormVersion()->sole();
            $answers = $version->reviewFormFields()->get()->mapWithKeys(fn (ReviewFormField $field): array => [
                $field->id => $field->fieldType?->name === 'number' ? (string) $score : 'Substansi dan metodologi sudah memadai.',
            ])->all();

            SubmitEvaluationAnswers::run($assignment, new EvaluationAnswersInput($answers, 'Layak dilanjutkan.'), $user);
        }
    }

    /** BR-REV-17 — catatan dibuka peserta putaran berjalan yang evaluasinya sudah beres dan belum Selesai. */
    public function openThread(FormSubmission $submission, Reviewer $reviewer, string $note): void
    {
        OpenReviewThread::run($submission->refresh(), $this->seat($submission, $reviewer), $note);
    }

    /** BR-REV-16 — peserta terakhir yang Selesai menyimpulkan putaran. */
    public function finish(FormSubmission $submission, Reviewer $reviewer): void
    {
        FinishReviewRound::run($this->seat($submission, $reviewer), $reviewer->user()->sole());
    }

    public function approve(FormSubmission $submission, User $operator): void
    {
        ApproveSubmission::run($submission->refresh(), $operator);
    }

    public function reject(FormSubmission $submission, User $operator, string $reason): void
    {
        RejectSubmission::run($submission->refresh(), $operator, $reason);
    }

    public function withdraw(FormSubmission $submission, User $operator, string $reason): void
    {
        WithdrawSubmission::run($submission->refresh(), $operator, $reason);
    }

    /** Peneliti memperbaiki abstrak sesuai catatan lalu mengirim ulang (putaran pemeriksaan dibuka). */
    public function revise(FormSubmission $submission, User $lead): void
    {
        $formKey = QaCatalogResult::formKeyOf($this->schemeKeyOf($submission));
        $form = $this->catalog->forms[$formKey];
        $submission->refresh();

        SaveWizardStep::run($submission, new WizardStepInput(new SubmissionAnswersInput(
            $form->id,
            $submission->submission_period_id,
            [$this->catalog->fields[$formKey]['abstrak']->id => fake('id_ID')->paragraph(5).' Metodologi pengambilan sampel diperjelas sesuai catatan penilai.'],
            [],
            $submission->updated_at?->toIso8601String(),
        ), 2, 3), $lead);

        ResubmitFormSubmission::run($submission->refresh(), $lead);
    }

    /**
     * Laporan Monev 1: Operator membuka siklus dan menugaskan penilai, ketua mengisi, opsional mengirim.
     * BR-MON-01 — hanya untuk induk Disetujui.
     */
    public function monev(FormSubmission $parent, User $operator, Reviewer $reviewer, bool $send): FormSubmission
    {
        $lead = $parent->submittedBy()->sole();
        $detail = $this->catalog->steps[QaCatalogResult::formKeyOf($this->schemeKeyOf($parent))]['monev1'];

        OpenMonevCycle::run($parent->refresh(), $detail, $operator);
        AssignCycleReviewer::run($parent->refresh(), $detail, $reviewer, $operator);

        $report = $parent->children()->where('form_phase_detail_id', $detail->id)->sole();
        $fields = $this->catalog->fields['monev'];

        SaveMonevReport::run($report, new MonevReportInput(
            [$fields['capaian']->id => 'Pengambilan data lapangan selesai 60%. Analisis awal menunjukkan hasil yang sejalan dengan hipotesis.'],
            [$fields['laporan']->id => QaFiles::pdf('laporan-kemajuan.pdf')],
            $report->updated_at?->toIso8601String(),
        ), $lead);

        if ($send) {
            SubmitMonevReport::run($report->refresh(), $lead);
        }

        return $report->refresh();
    }

    public function output(FormSubmission $submission, User $lead): void
    {
        SaveResearchOutput::run($submission->refresh(), $lead, new ResearchOutputInput('article', [
            'title' => $submission->title === null ? 'Artikel hasil penelitian' : 'Artikel: '.preg_replace('/^\[[^\]]+\]\s*/', '', $submission->title),
            'journal_name' => fake('id_ID')->randomElement(['Jurnal Teknologi Kalimantan', 'Jurnal Rekayasa Terapan', 'Jurnal Sains Borneo']),
            'journal_type' => 'national_accredited',
            'year' => (string) now()->year,
        ]));
    }

    private function seat(FormSubmission $submission, Reviewer $reviewer): SubmissionReviewer
    {
        return SubmissionReviewer::query()
            ->where('form_submission_id', $submission->id)
            ->where('reviewer_id', $reviewer->id)
            ->active()
            ->sole();
    }

    private function schemeKeyOf(FormSubmission $submission): string
    {
        foreach ($this->catalog->schemes as $key => $scheme) {
            if ($scheme->id === $submission->scheme_id) {
                return $key;
            }
        }

        return 'dasar';
    }

    /**
     * Jawaban satu langkah wizard: id isian => nilai, dan id isian => berkas. Nilai pilihan memakai bentuk
     * yang sama dengan layar (`WizardFieldOptions`): id opsi / skema / TKT sebagai string.
     *
     * @return array{0: array<int, string>, 1: array<int, UploadedFile>}
     */
    private function answersFor(int $step, string $formKey, string $schemeKey, string $title): array
    {
        $fields = $this->catalog->fields[$formKey];

        return match ($step) {
            2 => [[
                $fields['judul']->id => $title,
                $fields['abstrak']->id => fake('id_ID')->paragraph(6),
                $fields['kata_kunci']->id => implode(', ', fake('id_ID')->words(4)),
                $fields['mulai']->id => now()->addMonths(2)->startOfMonth()->toDateString(),
                $fields['bidang']->id => (string) $fields['bidang']->formFieldOptions()->orderBy('id')->get()->random()->id,
            ], []],
            3 => [$this->schemeAnswers($fields, $schemeKey), []],
            6 => [[], [$fields['proposal']->id => QaFiles::pdf('proposal.pdf')]],
            default => [[], []],
        };
    }

    /**
     * BR-SCH-05 — TKT dipilih dari izin skema terpilih.
     *
     * @param  array<string, FormField>  $fields
     * @return array<int, string>
     */
    private function schemeAnswers(array $fields, string $schemeKey): array
    {
        $scheme = $this->catalog->schemes[$schemeKey];
        $answers = [$fields['skema']->id => (string) $scheme->id];

        if (isset($fields['tkt'])) {
            $answers[$fields['tkt']->id] = (string) $scheme->allowedTrls()->orderBy('level')->firstOrFail()->id;
        }

        return $answers;
    }
}
