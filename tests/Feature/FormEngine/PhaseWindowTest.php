<?php

// tests/Feature/FormEngine/PhaseWindowTest.php
//
// BR-FE-17 + BR-FE-26 — deadline sebuah FormPhaseDetail mengikat **aktor detail itu
// sendiri**, bukan seluruh alur. Detail yang direview memegang tiga tenggat: Batas Submit
// (pengisi), Batas Evaluasi (reviewer form evaluasi yang melekat padanya), dan Batas Revisi
// (pengisi saat NEEDS_REVISION).
//
// Fixture lama memasang form evaluasi ke detail evaluasi terpisah — bentuk yang ditolak jalur
// produksi (PublishPhaseVersion). Akibatnya test lolos sementara produksi memakai Batas
// Submit peneliti sebagai tenggat reviewer (E1b, temuan 2). Di sini form evaluasi melekat pada
// detail ber-`needs_review`, sama seperti produksi.

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\Support\PhaseAccessGate;
use Symfony\Component\HttpKernel\Exception\HttpException;

function windowDate(SubmissionPeriod $period, string $label, string $when): SubmissionDate
{
    return SubmissionDate::create([
        ...deadlineTypeOf($label),
        'submission_period_id' => $period->id,
        'datetime' => $when,
    ]);
}

it('[FE-WINDOW-01] evaluasi tetap terbuka setelah batas submit lewat', function () {
    $fx = reviewedDetailFixture(now()->subDay()->toDateTimeString(), now()->addWeek()->toDateTimeString());

    // Tenggat peneliti sudah lewat, tapi reviewer punya jendelanya sendiri.
    expect($fx['detail']->isWithinDeadline($fx['period']))->toBeFalse()
        ->and($fx['detail']->isWithinDeadline($fx['period'], DeadlineType::Evaluation))->toBeTrue()
        ->and($fx['assignment']->due_date->equalTo(stepDate($fx['detail'], $fx['period'], DeadlineType::Evaluation)->datetime))->toBeTrue()
        ->and($fx['reviewer']->can('evaluate', $fx['assignment']))->toBeTrue();
});

it('[FE-WINDOW-02] evaluasi ditolak setelah batas evaluasinya sendiri lewat', function () {
    $fx = reviewedDetailFixture(now()->subWeek()->toDateTimeString(), now()->subDay()->toDateTimeString());

    expect($fx['detail']->isWithinDeadline($fx['period'], DeadlineType::Evaluation))->toBeFalse()
        ->and($fx['reviewer']->can('evaluate', $fx['assignment']))->toBeFalse();
});

it('[FE-WINDOW-03] revisi punya jendelanya sendiri', function () {
    $period = SubmissionPeriod::factory()->create();
    windowDate($period, 'Batas Pengiriman', now()->subDay()->toDateTimeString());
    windowDate($period, 'Batas Revisi', now()->addWeek()->toDateTimeString());
    $detail = FormPhaseDetail::factory()->forPeriod($period)->create();
    $revisi = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => NeedsRevision::class]);
    $draft = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => Draft::class]);

    // Tanpa jendela terpisah, janji BR-BUD-03 — anggaran tetap bisa disunting saat
    // NEEDS_REVISION — bergantung pada Batas Submit (E1b, temuan 3).
    expect(PhaseAccessGate::isOpenFor($revisi, $detail))->toBeTrue()
        ->and(PhaseAccessGate::isOpenFor($draft, $detail))->toBeFalse()
        ->and(PhaseAccessGate::deadlineFor($revisi, $detail)?->type)->toBe(DeadlineType::Revision);
});

it('[FE-WINDOW-03] tanpa Batas Revisi, revisi kembali memakai Batas Submit', function () {
    $period = SubmissionPeriod::factory()->create();
    $submit = windowDate($period, 'Batas Pengiriman', now()->addWeek()->toDateTimeString());
    $detail = FormPhaseDetail::factory()->forPeriod($period)->create();
    $revisi = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => NeedsRevision::class]);

    expect(PhaseAccessGate::deadlineFor($revisi, $detail)?->id)->toBe($submit->id)
        ->and(PhaseAccessGate::isOpenFor($revisi, $detail))->toBeTrue();
});

it('[FE-WINDOW-02] periode yang ditutup paksa menutup semua jendela', function () {
    $fx = reviewedDetailFixture(now()->addDay()->toDateTimeString(), now()->addWeek()->toDateTimeString());

    $fx['period']->update(['is_force_closed' => true]);

    // Force close mengalahkan tanggal per detail — sumbu yang berbeda (BR-SCH-07).
    expect($fx['detail']->fresh()->isWithinDeadline($fx['period']->fresh()))->toBeFalse()
        ->and($fx['detail']->fresh()->isWithinDeadline($fx['period']->fresh(), DeadlineType::Evaluation))->toBeFalse();
});

it('[FE-WINDOW-10] revisi digerbangi Batas Revisi, bukan Batas Submit', function () {
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $submission->forceFill(['status' => NeedsRevision::class, 'is_submitted' => true])->save();

    stepDate($fx['detail'], $fx['period'])->update(['datetime' => now()->subDay()]);
    windowDate($fx['period'], 'Batas Revisi', now()->addWeek()->toDateTimeString());

    $responses = [['form_field_id' => wizardTextField($fx['form'])->id, 'value' => 'Contoh nilai']];
    $save = fn () => $this->actingAs($fx['user'])->post(
        route('user.submissions.wizard.save', [$submission, 2]),
        wizardPayload($submission->fresh()->updated_at->toISOString(), 3, $responses),
    );

    $save()->assertSessionHasNoErrors()->assertRedirect(route('user.submissions.wizard', [$submission, 3]));

    $this->travel(8)->days();

    $save()->assertForbidden();
});

// Catatan: tests/Unit/FormPhaseDetailTest.php dihapus dan digantikan berkas
// ini. Versi unit itu merangkai periode pada `formPhase` — relasi yang tidak
// pernah bisa ada, karena tabel `form_phases` tidak punya kolom
// `submission_period_id`. Ia lulus justru karena stub-nya sendiri, sementara
// kode produksinya selalu fatal begitu benar-benar dipanggil.
//
// Pelajarannya: stub yang merangkai relasi dengan tangan bisa membuktikan
// sesuatu yang mustahil. Di sini semuanya record sungguhan.

// BR-FE-26 — tanggal yang belum diatur bukan tanggal yang sudah lewat. Peneliti yang dibilang
// "sudah lewat" mengira dirinya terlambat, padahal Operator belum mengisi jadwalnya.
it('[BR-FE-26] langkah tanpa tanggal di kalender ditolak dengan alasan jadwal belum diatur', function () {
    $period = SubmissionPeriod::factory()->create();
    $detail = FormPhaseDetail::factory()->forPeriod($period)->create(['deadline_type' => DeadlineType::Submit, 'deadline_cycle' => 2]);
    $draft = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => Draft::class]);

    expect(fn () => PhaseAccessGate::assertOpenFor($draft, $detail))
        ->toThrow(HttpException::class, 'Jadwal Laporan Monev II belum diatur di periode ini. Hubungi Operator LPPM.');
});

it('[BR-FE-08] langkah yang tanggalnya lewat tetap ditolak dengan alasan batas waktu', function () {
    $period = SubmissionPeriod::factory()->create();
    windowDate($period, 'Batas Pengiriman', now()->subDay()->toDateTimeString());
    $detail = FormPhaseDetail::factory()->forPeriod($period)->create();
    $draft = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => Draft::class]);

    expect(fn () => PhaseAccessGate::assertOpenFor($draft, $detail))
        ->toThrow(HttpException::class, 'Batas waktu tahap ini sudah lewat. Hubungi Operator LPPM untuk meminta perpanjangan.');
});
