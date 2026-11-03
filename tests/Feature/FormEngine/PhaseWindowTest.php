<?php

// tests/Feature/FormEngine/PhaseWindowTest.php
//
// BR-FE-17 — deadline sebuah FormPhaseDetail mengikat **aktor detail itu
// sendiri**, bukan seluruh alur.
//
// Ditemukan saat audit tiga sumbu (ADR 0014): contoh lifecycle memberi
// detail Pengajuan dan detail Evaluasi tanggal yang sama, "Batas Submit".
// Akibatnya reviewer tidak punya waktu kerja sama sekali, dan revisi
// mustahil karena detail researcher-nya sudah terkunci begitu status
// NEEDS_REVISION tercapai.

use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;

function detailWithDeadline(SubmissionPeriod $period, FormPhase $phase, string $label, string $when, int $order): FormPhaseDetail
{
    $date = SubmissionDate::create([
        ...deadlineTypeOf($label),
        'submission_period_id' => $period->id,
        'datetime' => $when,
    ]);

    return FormPhaseDetail::factory()->create([
        'form_phase_id' => $phase->id,
        'submission_date_id' => $date->id,
        'order' => $order,
        'needs_review' => $order === 2,
    ]);
}

/** @return array{0: SubmissionPeriod, 1: FormPhase} */
function windowFixture(): array
{
    $period = SubmissionPeriod::factory()->create(['is_force_closed' => false]);
    // form_phases tidak punya submission_period_id — periodenya menempel
    // pada SubmissionDate tiap detail, bukan pada fasenya.
    $phase = FormPhase::create([
        'title' => 'Fase Uji Jendela '.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);

    return [$period, $phase];
}

it('[FE-WINDOW-01] evaluasi tetap terbuka setelah batas submit lewat', function () {
    [$period, $phase] = windowFixture();

    $pengajuan = detailWithDeadline($period, $phase, 'Batas Submit', now()->subDay()->toDateTimeString(), 1);
    $evaluasi = detailWithDeadline($period, $phase, 'Batas Evaluasi', now()->addWeek()->toDateTimeString(), 2);

    // Tenggat researcher sudah lewat, tapi reviewer punya jendelanya sendiri.
    expect($pengajuan->isWithinDeadline())->toBeFalse()
        ->and($evaluasi->isWithinDeadline())->toBeTrue();
});

it('[FE-WINDOW-02] evaluasi ditolak setelah batas evaluasinya sendiri lewat', function () {
    [$period, $phase] = windowFixture();

    $evaluasi = detailWithDeadline($period, $phase, 'Batas Evaluasi', now()->subDay()->toDateTimeString(), 2);

    expect($evaluasi->isWithinDeadline())->toBeFalse();
});

it('[FE-WINDOW-03] revisi punya jendelanya sendiri', function () {
    [$period, $phase] = windowFixture();

    detailWithDeadline($period, $phase, 'Batas Submit', now()->subDay()->toDateTimeString(), 1);
    $revisi = detailWithDeadline($period, $phase, 'Batas Revisi', now()->addWeek()->toDateTimeString(), 3);

    // Tanpa jendela terpisah, janji BR-BUD-03 — anggaran tetap bisa disunting
    // saat NEEDS_REVISION — tidak bisa ditepati.
    expect($revisi->isWithinDeadline())->toBeTrue();
});

it('[FE-WINDOW-04] dua detail beraktor sama boleh berbagi satu tanggal', function () {
    [$period, $phase] = windowFixture();

    $date = SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'submission_period_id' => $period->id,
        'datetime' => now()->addWeek(),
    ]);

    // Yang dilarang berbagi tanggal adalah detail dengan aktor **berbeda**.
    // Dua tahap researcher berurutan wajar memakai batas yang sama.
    $satu = FormPhaseDetail::factory()->create([
        'form_phase_id' => $phase->id, 'submission_date_id' => $date->id,
        'order' => 1, 'needs_review' => false,
    ]);
    $dua = FormPhaseDetail::factory()->create([
        'form_phase_id' => $phase->id, 'submission_date_id' => $date->id,
        'order' => 2, 'needs_review' => false,
    ]);

    expect($satu->submission_date_id)->toBe($dua->submission_date_id)
        ->and($satu->isWithinDeadline())->toBeTrue()
        ->and($dua->isWithinDeadline())->toBeTrue();
});

it('[FE-WINDOW-02] periode yang ditutup paksa menutup semua jendela', function () {
    [$period, $phase] = windowFixture();

    $evaluasi = detailWithDeadline($period, $phase, 'Batas Evaluasi', now()->addWeek()->toDateTimeString(), 2);

    $period->update(['is_force_closed' => true]);

    // Force close mengalahkan tanggal per detail — sumbu yang berbeda.
    expect($evaluasi->fresh()->isWithinDeadline())->toBeFalse();
});

// Catatan: tests/Unit/FormPhaseDetailTest.php dihapus dan digantikan berkas
// ini. Versi unit itu merangkai periode pada `formPhase` — relasi yang tidak
// pernah bisa ada, karena tabel `form_phases` tidak punya kolom
// `submission_period_id`. Ia lulus justru karena stub-nya sendiri, sementara
// kode produksinya selalu fatal begitu benar-benar dipanggil.
//
// Pelajarannya: stub yang merangkai relasi dengan tangan bisa membuktikan
// sesuatu yang mustahil. Di sini semuanya record sungguhan.
