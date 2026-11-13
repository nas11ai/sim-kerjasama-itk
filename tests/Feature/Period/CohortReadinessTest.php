<?php

// tests/Feature/Period/CohortReadinessTest.php
//
// BR-FE-36 — cek kesiapan angkatan (Periode × Tahap) terhadap versi strukturnya: isu langkah
// Pengajuan dan urutan tanggal memblokir pengajuan baru, isu langkah sesudahnya hanya peringatan.

use App\Actions\FormSubmission\StartSubmission;
use App\Data\View\Period\CohortReadinessData;
use App\Data\View\Period\ReadinessCheckData;
use App\Enums\DeadlineType;
use App\Enums\ReadinessCheck;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\PhaseVersionPin;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Queries\Form\PhaseLibrary;
use App\Queries\Period\CohortReadiness;
use App\Queries\Period\SubmissionPeriodList;
use App\Queries\Submission\ResearcherStartOptions;
use App\Support\PhaseAccessGate;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EndToEndFlowSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

const NOT_READY = 'Tahap ini belum siap menerima pengajuan di periode ini. Hubungi Operator LPPM.';

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function readinessOperator(): User
{
    $user = User::factory()->create();
    $user->assignRole('operator');

    return $user;
}

function readyPhase(string $title = 'Hibah Internal'): FormPhase
{
    return FormPhase::create(['title' => $title, 'is_active' => true]);
}

function readinessOf(SubmissionPeriod $period, FormPhase $phase): CohortReadinessData
{
    return CohortReadiness::run([$period->id])[CohortReadiness::key($period->id, $phase->id)]
        ?? throw new RuntimeException('Angkatan tidak dinilai.');
}

function checkOf(CohortReadinessData $cohort, ReadinessCheck $check): ReadinessCheckData
{
    return collect($cohort->checks)->firstWhere('check', $check->value)
        ?? throw new RuntimeException("Cek {$check->value} tidak ada.");
}

/** @return list<array{message: string, blocking: bool}> */
function issuesOf(CohortReadinessData $cohort, ReadinessCheck $check): array
{
    return array_map(fn ($issue) => ['message' => $issue->message, 'blocking' => $issue->blocking], checkOf($cohort, $check)->issues);
}

/** Langkah 1 fixture wizard diganti salinan yang direview, dengan Form Evaluasi wajib bila `$withForm`. */
function reviewedWizardStep(array $fx, bool $withForm = true, bool $required = true): FormPhaseDetail
{
    $detail = restep($fx['detail'], ['needs_review' => true]);

    if ($withForm) {
        evaluationFormOn($detail, required: $required);
    }

    return $detail;
}

/** Langkah lain di tahap fixture wizard, dengan aturan akses yang sama (bisa diakses peneliti). */
function wizardStepAt(array $fx, int $order, ?int $cycle, bool $needsReview, DeadlineType $type = DeadlineType::Submit): FormPhaseDetail
{
    return phaseStep($fx['phase'], [
        'form_access_control_id' => $fx['detail']->form_access_control_id,
        'phase_type_id' => $fx['detail']->phase_type_id,
        'order' => $order,
        'needs_review' => $needsReview,
        'deadline_type' => $type,
        'deadline_cycle' => $cycle,
    ]);
}

/**
 * Tahap kedua yang bisa diakses peneliti fixture, ditautkan ke periode fixture dan siap (tanpa review).
 *
 * @return array{form: Form, phase: FormPhase, detail: FormPhaseDetail}
 */
function secondWizardCohort(array $fx, string $title): array
{
    $form = Form::factory()->create();
    $access = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $fx['detail']->formAccessControl?->organization_id,
    ]);
    $phase = readyPhase($title);
    $detail = phaseStep($phase, [
        'form_access_control_id' => $access->id,
        'phase_type_id' => $fx['detail']->phase_type_id,
        'order' => 1,
        'deadline_type' => DeadlineType::Submit,
    ]);
    SubmissionPeriodPhase::create(['submission_period_id' => $fx['period']->id, 'form_phase_id' => $phase->id]);

    return ['form' => $form, 'phase' => $phase, 'detail' => $detail];
}

/** @return list<int>|null id Form yang ditawarkan langkah 1 wizard di `$period`; null = periode tak ditawarkan */
function readinessOffered(User $user, SubmissionPeriod $period): ?array
{
    $option = collect(ResearcherStartOptions::run($user))->firstWhere('id', $period->id);

    return $option === null ? null : array_map(fn ($form) => $form->formId, $option->forms);
}

function expectStartRefused(array $fx, ?FormPhaseDetail $detail = null): void
{
    try {
        StartSubmission::run($fx['form']->id, $fx['period']->id, $detail ?? $fx['detail'], $fx['user']);
        test()->fail('Harus ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors()['form_phase_id'] ?? null)->toBe([NOT_READY]);
    }

    expect(FormSubmission::query()->where('form_id', $fx['form']->id)->count())->toBe(0);
}

it('[FE-READY-01] Batas Evaluasi langkah Pengajuan yang belum diatur menolak pengajuan baru', function () {
    $fx = wizardFixture();
    $detail = reviewedWizardStep($fx);

    expectStartRefused($fx, $detail);
    expect(readinessOffered($fx['user'], $fx['period']))->toBeNull()
        ->and(issuesOf(readinessOf($fx['period'], $fx['phase']), ReadinessCheck::MissingDate))
        ->toBe([['message' => 'Batas Evaluasi belum diatur.', 'blocking' => true]]);

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.store'), [
            'form_id' => $fx['form']->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_id' => $fx['phase']->id,
        ])
        ->assertSessionHasErrors(['form_phase_id' => NOT_READY]);
});

it('[FE-READY-02] angkatan lain di periode yang sama tetap menerima pengajuan', function () {
    $fx = wizardFixture();
    reviewedWizardStep($fx);
    $other = secondWizardCohort($fx, 'Hibah Mandiri');

    $started = StartSubmission::run($other['form']->id, $fx['period']->id, $other['detail'], $fx['user']);

    expect($started->form_phase_detail_id)->toBe($other['detail']->id)
        ->and(readinessOffered($fx['user'], $fx['period']))->toBe([$other['form']->id])
        ->and(readinessOf($fx['period'], $other['phase'])->status())->toBe('ready');
});

it('[FE-READY-03] angkatan yang sudah punya pengajuan tidak diblokir; cacatnya tampil sebagai "Perlu perbaikan"', function () {
    $fx = wizardFixture();
    $detail = reviewedWizardStep($fx);
    PhaseVersionPin::create([
        'submission_period_id' => $fx['period']->id,
        'form_phase_id' => $fx['phase']->id,
        'form_phase_version_id' => $detail->form_phase_version_id,
        'pinned_at' => now(),
    ]);
    scheduledSubmission($detail, $fx['period']);

    $started = StartSubmission::run($fx['form']->id, $fx['period']->id, $detail, $fx['user']);
    $cohort = readinessOf($fx['period'], $fx['phase']);

    expect($started->submitted_by)->toBe($fx['user']->id)
        ->and($cohort->running)->toBeTrue()
        ->and($cohort->blocksNewSubmissions())->toBeFalse()
        ->and($cohort->status())->toBe('needs_fix');
});

it('[FE-READY-04] Batas Evaluasi sebelum Batas Submit menggagalkan cek urutan', function () {
    $phase = readyPhase();
    evaluationFormOn(scheduleStep($phase, needsReview: true));
    $period = schedulePeriod($phase, 'Periode 2026');
    calendarDate($period, DeadlineType::Submit, '2027-06-30 23:59:00');
    calendarDate($period, DeadlineType::Evaluation, '2027-06-20 23:59:00');

    $cohort = readinessOf($period, $phase);

    expect(issuesOf($cohort, ReadinessCheck::DateOrder))
        ->toBe([['message' => 'Batas Evaluasi tidak boleh lebih awal dari Batas Submit.', 'blocking' => true]])
        ->and(checkOf($cohort, ReadinessCheck::DateOrder)->state())->toBe('failed')
        ->and($cohort->status())->toBe('blocked');
});

it('[FE-READY-05] langkah Pengajuan direview tanpa Form Evaluasi wajib aktif menggagalkan cek', function () {
    $fx = wizardFixture();
    $detail = reviewedWizardStep($fx, required: false);
    calendarDate($fx['period'], DeadlineType::Evaluation, now()->addWeeks(2));

    $cohort = readinessOf($fx['period'], $fx['phase']);

    expect(issuesOf($cohort, ReadinessCheck::RequiredEvaluationForm))->toBe([[
        'message' => 'Langkah 1 (Pengajuan) direview, tapi belum punya Form Evaluasi wajib yang aktif.',
        'blocking' => true,
    ]])
        ->and(checkOf($cohort, ReadinessCheck::MissingDate)->state())->toBe('passed')
        ->and($cohort->status())->toBe('blocked');
    expectStartRefused($fx, $detail);
});

it('[FE-READY-06] Operator melihat daftar cek per angkatan; tabel periode menghitung angkatan yang belum siap', function () {
    $fx = wizardFixture();
    $fx['phase']->update(['title' => 'Hibah Internal']);
    reviewedWizardStep($fx);
    secondWizardCohort($fx, 'Hibah Mandiri');

    $this->actingAs(readinessOperator())
        ->get(route('admin.submission-periods.readiness', $fx['period']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PeriodReadinessPage')
            ->where('period', ['id' => $fx['period']->id, 'name' => $fx['period']->name])
            ->has('cohorts', 2)
            ->where('cohorts.0.phaseTitle', 'Hibah Internal')
            ->where('cohorts.0.status', 'blocked')
            ->where('cohorts.0.versionLabel', 'Versi 1')
            ->where('cohorts.0.running', false)
            ->where('cohorts.0.checks.0.title', 'Tanggal setiap langkah sudah diatur')
            ->where('cohorts.0.checks.0.state', 'failed')
            ->where('cohorts.0.checks.0.issues.0', ['message' => 'Batas Evaluasi belum diatur.', 'blocking' => true])
            ->where('cohorts.0.checks.1.title', 'Urutan tanggal masuk akal')
            ->where('cohorts.0.checks.1.state', 'passed')
            ->where('cohorts.0.checks.2.title', 'Langkah yang direview punya Form Evaluasi wajib')
            ->where('cohorts.1.phaseTitle', 'Hibah Mandiri')
            ->where('cohorts.1.status', 'ready'));

    $this->actingAs(readinessOperator())
        ->get(route('admin.submission-periods.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('periods.data.0.id', $fx['period']->id)
            ->where('periods.data.0.unreadyCohortCount', 1));
});

it('[FE-READY-07] tanggal dan Form Evaluasi langkah monev yang belum diatur tidak menahan pengajuan baru', function () {
    $fx = wizardFixture();
    $monev = wizardStepAt($fx, order: 3, cycle: 1, needsReview: true);

    $cohort = readinessOf($fx['period'], $fx['phase']);

    expect($cohort->status())->toBe('pending')
        ->and(checkOf($cohort, ReadinessCheck::MissingDate)->state())->toBe('warning')
        ->and(issuesOf($cohort, ReadinessCheck::MissingDate))->toBe([
            ['message' => 'Batas Laporan Monev I belum diatur, langkah Laporan Monev I tertutup sampai tanggalnya diatur.', 'blocking' => false],
            ['message' => 'Batas Evaluasi Monev I belum diatur, evaluasi Laporan Monev I tak bertenggat sampai tanggalnya diatur.', 'blocking' => false],
        ])
        ->and(checkOf($cohort, ReadinessCheck::RequiredEvaluationForm)->state())->toBe('warning');

    $started = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    expect($started->form_phase_detail_id)->toBe($fx['detail']->id)
        ->and(readinessOffered($fx['user'], $fx['period']))->toBe([$fx['form']->id]);

    $report = scheduledSubmission($monev, $fx['period'], ['parent_submission_id' => $started->id]);

    expect(fn () => PhaseAccessGate::assertOpenFor($report, $monev))
        ->toThrow(HttpException::class, 'Jadwal Laporan Monev I belum diatur di periode ini. Hubungi Operator LPPM.');
});

it('[FE-WINDOW-08] mendaftar celah Batas Evaluasi langkah yang direview', function () {
    $phase = readyPhase('Fase Celah');
    $step = scheduleStep($phase, needsReview: true);
    $period = schedulePeriod($phase, 'Periode A');
    calendarDate($period, DeadlineType::Submit, now()->addWeek());

    expect(issuesOf(readinessOf($period, $phase), ReadinessCheck::MissingDate))
        ->toBe([['message' => 'Batas Evaluasi belum diatur.', 'blocking' => true]]);

    $form = $step->formAccessControl?->form;
    expect($form)->not->toBeNull();
    $card = collect(PhaseLibrary::run($form))->firstWhere('id', $phase->id);

    expect($card?->deadlineGaps)->toBe([['period' => 'Periode A', 'count' => 1]]);
});

it('mendaftar tanggal kerja yang hilang, tetapi tidak Batas Evaluasi langkah yang tidak direview', function () {
    $phase = readyPhase('Fase Celah');
    scheduleStep($phase, cycle: 1, needsReview: false, order: 3);
    $period = schedulePeriod($phase, 'Periode B');

    expect(issuesOf(readinessOf($period, $phase), ReadinessCheck::MissingDate))->toBe([[
        'message' => 'Batas Laporan Monev I belum diatur, langkah Laporan Monev I tertutup sampai tanggalnya diatur.',
        'blocking' => false,
    ]]);
});

it('judges readiness on every step even when the researcher can access only the submission step', function () {
    $fx = wizardFixture();
    // Aturan akses organisasi lain: peneliti fixture tidak bisa mengakses langkah monev ini.
    scheduleStep($fx['phase'], cycle: 1, needsReview: false, order: 3);
    calendarDate($fx['period'], DeadlineType::Submit, now()->addDay(), cycle: 1);

    expect(readinessOffered($fx['user'], $fx['period']))->toBeNull()
        ->and(issuesOf(readinessOf($fx['period'], $fx['phase']), ReadinessCheck::DateOrder))
        ->toBe([['message' => 'Batas Laporan Monev I tidak boleh lebih awal dari Batas Submit.', 'blocking' => true]]);
    expectStartRefused($fx);
});

it('checks the pinned version of a running cohort, not the latest', function () {
    $phase = readyPhase();
    $v1Step = scheduleStep($phase, needsReview: false);
    $running = schedulePeriod($phase, 'Periode Lama');
    calendarDate($running, DeadlineType::Submit, now()->addWeek());
    PhaseVersionPin::create([
        'submission_period_id' => $running->id,
        'form_phase_id' => $phase->id,
        'form_phase_version_id' => $v1Step->form_phase_version_id,
        'pinned_at' => now(),
    ]);
    scheduledSubmission($v1Step, $running);
    $v2 = phaseVersion($phase, [['needs_review' => true] + $v1Step->only(['form_access_control_id', 'phase_type_id', 'order', 'deadline_type', 'deadline_cycle'])]);
    evaluationFormOn($v2->formPhaseDetails()->firstOrFail());
    $fresh = schedulePeriod($phase, 'Periode Baru');
    calendarDate($fresh, DeadlineType::Submit, now()->addWeek());

    $pinned = readinessOf($running, $phase);
    $latest = readinessOf($fresh, $phase);

    expect($pinned->status())->toBe('ready')
        ->and($pinned->versionLabel)->toBe('Versi 1')
        ->and($pinned->running)->toBeTrue()
        ->and($latest->status())->toBe('blocked')
        ->and($latest->versionLabel)->toBe('Versi 2')
        ->and($latest->running)->toBeFalse();
});

it('computes readiness for a page of periods in a constant number of queries', function () {
    $filters = ['search' => '', 'status' => '', 'year' => ''];
    $readinessQueries = function () use ($filters): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        SubmissionPeriodList::run($filters);
        $pins = collect(DB::getQueryLog())->filter(fn (array $q) => str_starts_with($q['query'], 'select * from "phase_version_pins"'))->count();
        DB::flushQueryLog();
        DB::enableQueryLog();
        CohortReadiness::run(SubmissionPeriod::query()->pluck('id')->all());
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$pins, $total];
    };
    $cohort = function (int $i): void {
        $phase = readyPhase("Tahap {$i}");
        evaluationFormOn(scheduleStep($phase, needsReview: true));
        scheduleStep($phase, cycle: 1, needsReview: false, order: 3);
        $period = schedulePeriod($phase, "Periode {$i}");
        calendarDate($period, DeadlineType::Submit, now()->addWeek());
    };

    $cohort(1);
    $one = $readinessQueries();
    $cohort(2);
    $cohort(3);

    expect($readinessQueries())->toBe($one)
        ->and($one[0])->toBe(1);
});

it('warns, not blocks, on a monev step without a required evaluation form', function () {
    $fx = wizardFixture();
    wizardStepAt($fx, order: 3, cycle: 1, needsReview: true);
    calendarDate($fx['period'], DeadlineType::Submit, now()->addMonths(6), cycle: 1);
    calendarDate($fx['period'], DeadlineType::Evaluation, now()->addMonths(7), cycle: 1);

    $cohort = readinessOf($fx['period'], $fx['phase']);

    expect(issuesOf($cohort, ReadinessCheck::RequiredEvaluationForm))->toBe([[
        'message' => 'Langkah 3 (Laporan Monev I) direview, tapi belum punya Form Evaluasi wajib yang aktif.',
        'blocking' => false,
    ]])
        ->and(checkOf($cohort, ReadinessCheck::MissingDate)->state())->toBe('passed')
        ->and($cohort->status())->toBe('pending')
        ->and($cohort->blocksNewSubmissions())->toBeFalse();
});

it('blocks on an order violation that involves a monev date', function () {
    $phase = readyPhase();
    scheduleStep($phase, needsReview: false);
    scheduleStep($phase, cycle: 1, needsReview: false, order: 3);
    scheduleStep($phase, cycle: 2, needsReview: false, order: 5);
    $period = schedulePeriod($phase, 'Periode 2026');
    calendarDate($period, DeadlineType::Submit, '2027-06-30 23:59:00');
    calendarDate($period, DeadlineType::Submit, '2027-06-01 23:59:00', cycle: 1);

    $cohort = readinessOf($period, $phase);

    expect(issuesOf($cohort, ReadinessCheck::DateOrder))
        ->toBe([['message' => 'Batas Laporan Monev I tidak boleh lebih awal dari Batas Submit.', 'blocking' => true]])
        ->and($cohort->status())->toBe('blocked');

    // Tanggal yang kosong di tengah tidak memutus perbandingan: Monev II dibanding Batas Submit.
    $gapped = schedulePeriod($phase, 'Periode 2027');
    calendarDate($gapped, DeadlineType::Submit, '2027-06-30 23:59:00');
    calendarDate($gapped, DeadlineType::Submit, '2027-06-01 23:59:00', cycle: 2);

    expect(issuesOf(readinessOf($gapped, $phase), ReadinessCheck::DateOrder))
        ->toBe([['message' => 'Batas Laporan Monev II tidak boleh lebih awal dari Batas Submit.', 'blocking' => true]]);
});

it('does not compare dates that are not set', function () {
    $phase = readyPhase();
    scheduleStep($phase, needsReview: true);
    scheduleStep($phase, cycle: 1, needsReview: false, order: 3);
    $period = schedulePeriod($phase, 'Periode 2026');
    calendarDate($period, DeadlineType::Submit, '2027-06-30 23:59:00');

    $cohort = readinessOf($period, $phase);

    expect(checkOf($cohort, ReadinessCheck::DateOrder)->state())->toBe('passed')
        ->and(checkOf($cohort, ReadinessCheck::DateOrder)->issues)->toBe([]);
});

it('compares Batas Revisi with Batas Submit when Batas Evaluasi is missing', function () {
    $phase = readyPhase();
    scheduleStep($phase, needsReview: true);
    $period = schedulePeriod($phase, 'Periode 2026');
    calendarDate($period, DeadlineType::Submit, '2027-06-30 23:59:00');
    calendarDate($period, DeadlineType::Revision, '2027-06-10 23:59:00');

    expect(issuesOf(readinessOf($period, $phase), ReadinessCheck::DateOrder))
        ->toBe([['message' => 'Batas Revisi tidak boleh lebih awal dari Batas Submit.', 'blocking' => true]]);

    calendarDate($period, DeadlineType::Evaluation, '2027-07-10 23:59:00');

    expect(issuesOf(readinessOf($period->fresh() ?? $period, $phase), ReadinessCheck::DateOrder))
        ->toBe([['message' => 'Batas Revisi tidak boleh lebih awal dari Batas Evaluasi.', 'blocking' => true]]);
});

it('accepts equal dates', function () {
    $phase = readyPhase();
    evaluationFormOn(scheduleStep($phase, needsReview: true));
    scheduleStep($phase, cycle: 1, needsReview: false, order: 3);
    $period = schedulePeriod($phase, 'Periode 2026');
    foreach ([[DeadlineType::Submit, null], [DeadlineType::Evaluation, null], [DeadlineType::Revision, null], [DeadlineType::Submit, 1]] as [$type, $cycle]) {
        calendarDate($period, $type, '2027-06-30 23:59:00', cycle: $cycle);
    }

    $cohort = readinessOf($period, $phase);

    expect(checkOf($cohort, ReadinessCheck::DateOrder)->state())->toBe('passed')
        ->and($cohort->status())->toBe('ready');
});

it('hides the readiness page from users without periods.manage', function () {
    $phase = readyPhase();
    $period = schedulePeriod($phase, 'Periode 2026');
    [$researcher] = actingResearcher();

    $this->actingAs($researcher)
        ->get(route('admin.submission-periods.readiness', $period))
        ->assertForbidden();
});

it('leaves no seeded cohort blocked', function () {
    // Pemilik 2026-11-10 — seeder mengikuti setiap perubahan aturan; angkatan seed tidak boleh "Belum siap".
    $this->seed([DatabaseSeeder::class, EndToEndFlowSeeder::class]);

    $statuses = array_map(
        fn (CohortReadinessData $cohort): string => "{$cohort->phaseTitle}: {$cohort->status()}",
        CohortReadiness::run(SubmissionPeriod::query()->pluck('id')->all()),
    );

    expect($statuses)->not->toBeEmpty()
        ->and(array_filter($statuses, fn (string $s): bool => str_ends_with($s, ': blocked')))->toBe([]);
});

it('does not count a never-used cohort of a fully closed period as unready', function () {
    // Sama dengan kartu fase (PeriodScheduleGaps::gapAt): periode tutup tanpa pengajuan tidak dikejar.
    $phase = readyPhase();
    $step = scheduleStep($phase, needsReview: true);
    $closed = schedulePeriod($phase, 'Periode 2025');
    calendarDate($closed, DeadlineType::Submit, now()->subMonth());
    $running = schedulePeriod($phase, 'Periode 2024');
    calendarDate($running, DeadlineType::Submit, now()->subYear());
    PhaseVersionPin::create([
        'submission_period_id' => $running->id,
        'form_phase_id' => $phase->id,
        'form_phase_version_id' => $step->form_phase_version_id,
        'pinned_at' => now(),
    ]);

    $counts = collect(SubmissionPeriodList::run(['search' => '', 'status' => '', 'year' => ''])['periods']->items())
        ->mapWithKeys(fn ($row) => [$row->name => $row->unreadyCohortCount])
        ->all();

    expect($counts)->toBe(['Periode 2024' => 1, 'Periode 2025' => 0]);
});
