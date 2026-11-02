<?php

// tests/Feature/Submission/ArchivalTest.php
//
// BR-SM-13 — isArchived() computed, bukan kolom: belum disubmit DAN periode
// sudah tutup. Tidak ada yang "menyetel" arsip; ia turunan keadaan.
//
// BR-SM-10 — draft yang diarsipkan berubah read-only, tapi tidak pernah
// dihapus. Periode yang tutup membekukan, bukan membuang.

use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;

/** @return array{0: User, 1: FormSubmission, 2: SubmissionPeriod} */
function archiveFixture(bool $submitted = false): array
{
    $owner = User::factory()->create();
    $period = SubmissionPeriod::factory()->create(['is_force_closed' => false]);

    $label = SubmissionDateLabel::create(['name' => 'Batas '.fake()->unique()->numerify('####')]);
    SubmissionDate::create([
        'submission_date_label_id' => $label->id,
        'submission_period_id' => $period->id,
        'datetime' => now()->addWeek(),
    ]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => null,
        'status' => Draft::class,
        'is_submitted' => $submitted,
    ]);

    return [$owner, $submission, $period];
}

it('[SM-ARCHIVE-04] belum disubmit dan periode tutup berarti diarsipkan', function () {
    [, $submission, $period] = archiveFixture();

    expect($submission->fresh()->is_archived)->toBeFalse();

    $period->submissionDates()->update(['datetime' => now()->subDay()]);

    expect($submission->fresh()->is_archived)->toBeTrue();
});

it('[SM-ARCHIVE-05] sudah disubmit tidak ikut diarsipkan', function () {
    [, $submission, $period] = archiveFixture(submitted: true);

    $period->submissionDates()->update(['datetime' => now()->subDay()]);

    // Pengajuan yang sudah berjalan tidak boleh ikut beku hanya karena
    // periodenya lewat — ia sedang dinilai.
    expect($submission->fresh()->is_archived)->toBeFalse();
});

it('[SM-ARCHIVE-06] force closed memicu arsip meski tanggal belum lewat', function () {
    [, $submission, $period] = archiveFixture();

    $period->update(['is_force_closed' => true]);

    expect($submission->fresh()->is_archived)->toBeTrue();
});

it('[SM-ARCHIVE-01] draft yang diarsipkan tetap ada, tidak dihapus', function () {
    [, $submission, $period] = archiveFixture();

    $period->update(['is_force_closed' => true]);

    expect(FormSubmission::find($submission->id))->not->toBeNull()
        ->and($submission->fresh()->is_archived)->toBeTrue()
        ->and($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-ARCHIVE-03] draft yang diarsipkan tidak bisa disunting', function () {
    [$owner, $submission, $period] = archiveFixture();

    expect($owner->can('update', $submission->fresh()))->toBeTrue();

    $period->update(['is_force_closed' => true]);

    // Statusnya masih DRAFT, jadi pemeriksaan status saja tidak cukup —
    // arsip adalah sumbu kedua yang harus ikut menutup penyuntingan.
    expect($owner->can('update', $submission->fresh()))->toBeFalse();
});

it('[SM-ARCHIVE-04] periode tanpa tanggal belum terhitung lewat', function () {
    $owner = User::factory()->create();
    $period = SubmissionPeriod::factory()->create(['is_force_closed' => false]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => null,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    // Kasus hampa: `every()` atas koleksi kosong bernilai true, jadi tanpa
    // penjagaan khusus periode yang baru dibuat langsung mengarsipkan
    // seluruh draft di dalamnya.
    expect($submission->fresh()->is_archived)->toBeFalse()
        ->and($owner->can('update', $submission->fresh()))->toBeTrue();
});
