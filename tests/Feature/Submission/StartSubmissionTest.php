<?php

// tests/Feature/Submission/StartSubmissionTest.php
//
// ADR 0012 — baris form_submissions lahir di langkah 1 supaya created_at
// menjadi jangkar Temporal Field Binding (BR-FE-11).

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\DeadlineType;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Queries\Submission\ResearcherStartOptions;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('[SM-WIZARD-01] langkah 1 membuat draft beserta jangkar temporalnya', function () {
    $fx = wizardFixture();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->last_completed_step)->toBe(1)
        ->and($submission->is_submitted)->toBeFalse()
        ->and($submission->submission_period_id)->toBe($fx['period']->id)
        ->and($submission->form_phase_detail_id)->toBe($fx['detail']->id)
        // Jangkar temporal: created_at harus benar-benar tersimpan, bukan null.
        ->and($submission->created_at)->not->toBeNull();
});

it('memakai ulang draft yang sudah ada alih-alih membuat kembar', function () {
    $fx = wizardFixture();

    $first = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $second = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($second->id)->toBe($first->id)
        ->and(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(1);
});

it('[SM-WIZARD-12] pengajuan yang berakhir terminal tidak memblokir draft baru', function () {
    $fx = wizardFixture();

    $rejected = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Rejected::class,
        'is_submitted' => true,
    ]);

    $fresh = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($fresh->id)->not->toBe($rejected->id)
        ->and($fresh->status)->toBeInstanceOf(Draft::class)
        // Baris lama tidak disentuh — status terminal itu beku.
        ->and($rejected->fresh()->status)->toBeInstanceOf(Rejected::class);
});

it('memakai ulang pengajuan yang sedang direvisi, bukan membuat yang baru', function () {
    $fx = wizardFixture();

    $revising = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => NeedsRevision::class,
        'is_submitted' => true,
    ]);

    $resolved = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($resolved->id)->toBe($revising->id);
});

// BR-FE-01 — periode yang sudah ditutup tidak melahirkan pengajuan baru.
//
// Predikat "tertutup"-nya sengaja satu dengan yang dipakai BR-SM-13 untuk
// mengarsipkan draft (SubmissionPeriodGate). Dua salinan pasti menyimpang:
// yang satu akan ingat is_force_closed, yang lain tidak — dan persis itu yang
// terjadi pada isPeriodActive() warisan.

it('[FE-ACCESS-07] pengajuan tidak bisa dibuat saat periode ditutup paksa', function () {
    $fx = wizardFixture();
    $fx['period']->update(['is_force_closed' => true]);

    expect(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(HttpException::class)
        ->and(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(0);
});

it('[FE-ACCESS-07] jalur HTTP ikut menolak periode yang ditutup paksa', function () {
    $fx = wizardFixture();
    $fx['period']->update(['is_force_closed' => true]);

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.store'), [
            'form_id' => $fx['form']->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_id' => $fx['phase']->id,
        ])
        ->assertForbidden();

    expect(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(0);
});

it('[FE-ACCESS-07] periode yang seluruh batasnya sudah lewat juga menolak', function () {
    $fx = wizardFixture();
    $fx['period']->submissionDates()->update(['datetime' => now()->subWeek()]);

    expect(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(HttpException::class);
});

it('[FE-ACCESS-08] pengajuan bisa dibuat saat periode masih berjalan', function () {
    $fx = wizardFixture();

    expect($fx['period']->is_force_closed)->toBeFalse();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->last_completed_step)->toBe(1);
});

it('[FE-ACCESS-08] batas yang sebagian masih di depan menjaga periode tetap terbuka', function () {
    $fx = wizardFixture();

    // Seluruh SubmissionDate adalah batas akhir, bukan jendela mulai–selesai.
    // Satu batas yang sudah lewat tidak menutup periode selama masih ada yang
    // di depan — predikat warisan yang membacanya sebagai jendela justru
    // menolak pengajuan sebelum batas pertama tiba.
    SubmissionDate::create([
        'type' => DeadlineType::Evaluation,
        'datetime' => now()->subDay(),
        'submission_period_id' => $fx['period']->id,
    ]);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class);
});

it('[FE-ACCESS-09] pengajuan baru ditolak setelah Batas Submit meski Batas Evaluasi masih di depan', function () {
    $fx = wizardFixture();

    // BR-FE-01 (K8d) — yang membuka periode bagi pengajuan baru hanya Batas Submit siklus
    // utamanya; tanggal lain di depan milik tahap sesudah submit (E1b, temuan 4).
    $fx['period']->submissionDates()->update(['datetime' => now()->subDay()]);
    SubmissionDate::create([
        'type' => DeadlineType::Evaluation,
        'datetime' => now()->addMonth(),
        'submission_period_id' => $fx['period']->id,
    ]);

    expect(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(HttpException::class, 'Periode pengajuan sudah berakhir.');
});

it('[FE-ACCESS-09] periode tanpa Batas Submit belum menerima pengajuan', function () {
    $fx = wizardFixture();
    $fx['period']->submissionDates()->update(['type' => DeadlineType::Evaluation]);

    expect(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(HttpException::class);
});

it('draft yang sudah ada tetap bisa dilanjutkan di Form nonaktif', function () {
    $fx = wizardFixture();
    $draft = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $fx['form']->update(['is_active' => false]);

    // BR-FE-19 / W77 — draft yang masih boleh disunting bukan pengajuan baru.
    $again = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($again->id)->toBe($draft->id);
});

it('Form nonaktif tidak ditawarkan dan ditolak saat dimulai', function () {
    $fx = wizardFixture();
    wizardQueryPhaseInPeriod($fx['period']->id, $fx['phase']->id);

    $offeredForm = fn () => collect(ResearcherStartOptions::run($fx['user']))
        ->flatMap(fn ($period) => $period->forms)->firstWhere('formId', $fx['form']->id);

    // Kontrol: selagi aktif, Form ini memang ditawarkan.
    expect($offeredForm())->not->toBeNull();

    $fx['form']->update(['is_active' => false]);

    expect($offeredForm())->toBeNull()
        ->and(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(ValidationException::class);

    // Peneliti yang sudah punya draft tetap melihat Form itu, dengan draftId terisi.
    $fx['form']->update(['is_active' => true]);
    $draft = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $fx['form']->update(['is_active' => false]);

    expect($offeredForm()?->draftId)->toBe($draft->id);
});

it('draft yang sudah ada tetap bisa dilanjutkan di tahap nonaktif', function () {
    $fx = wizardFixture();
    $draft = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $fx['phase']->update(['is_active' => false]);

    // BR-FE-20 / W77 — draft yang masih boleh disunting bukan pengajuan baru.
    $again = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($again->id)->toBe($draft->id);
});

it('tahap nonaktif tidak ditawarkan kecuali ada draft', function () {
    $fx = wizardFixture();
    wizardQueryPhaseInPeriod($fx['period']->id, $fx['phase']->id);

    $offeredForm = fn () => collect(ResearcherStartOptions::run($fx['user']))
        ->flatMap(fn ($period) => $period->forms)->firstWhere('formId', $fx['form']->id);

    expect($offeredForm())->not->toBeNull();

    $fx['phase']->update(['is_active' => false]);

    expect($offeredForm())->toBeNull();

    $fx['phase']->update(['is_active' => true]);
    $draft = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $fx['phase']->update(['is_active' => false]);

    expect($offeredForm()?->draftId)->toBe($draft->id);
});

/** Periode kedua yang terbuka dan memakai tahap yang sama; Batas Submit detail tetap milik periode pertama. */
function windowSecondPeriod(array $fx): SubmissionPeriod
{
    $second = SubmissionPeriod::factory()->create();
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => now()->addWeeks(2),
        'submission_period_id' => $second->id,
    ]);
    foreach ([$fx['period'], $second] as $period) {
        $fx['phase']->submissionPeriodPhases()->create(['submission_period_id' => $period->id]);
    }

    return $second;
}

it('[FE-WINDOW-13] detail yang tenggatnya milik periode lain tidak membuka pengajuan di periode itu', function () {
    $fx = wizardFixture();
    $second = windowSecondPeriod($fx);

    $offeredIn = fn (SubmissionPeriod $period) => collect(ResearcherStartOptions::run($fx['user']))
        ->firstWhere('id', $period->id)?->forms;

    try {
        StartSubmission::run($fx['form']->id, $second->id, $fx['detail'], $fx['user']);
        $this->fail('Harus ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors()['form_phase_id'] ?? null)->toBe(['Tahap ini belum bertenggat di periode ini.']);
    }

    expect(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(0)
        ->and($offeredIn($second))->toBeNull()
        ->and($offeredIn($fx['period']))->toHaveCount(1);

    $started = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($started->submission_period_id)->toBe($fx['period']->id);
});

it('draft yang sudah ada di periode itu tetap bisa dilanjutkan', function () {
    $fx = wizardFixture();
    $second = windowSecondPeriod($fx);
    $draft = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $second->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    $offered = collect(ResearcherStartOptions::run($fx['user']))->firstWhere('id', $second->id)?->forms;

    expect(StartSubmission::run($fx['form']->id, $second->id, $fx['detail'], $fx['user'])->id)->toBe($draft->id)
        ->and($offered)->toHaveCount(1)
        ->and($offered[0]->draftId)->toBe($draft->id);
});
