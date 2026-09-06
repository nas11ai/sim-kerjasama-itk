<?php

// tests/Feature/Submission/TerminalOverrideTest.php
//
// BR-SM-05 — pengajuan berstatus terminal tidak bisa diubah kembali
// **kecuali lewat override Operator**.
//
// Override adalah satu-satunya pengecualian, dan sifatnya sempit: ber-scope
// ke satu submission + satu FormPhaseDetail (BR-FE-10), wajib beralasan
// (BR-FE-09), dan kedaluwarsa sendiri (ADR 0014). Ia bukan permission
// permanen — itu bedanya dengan sekadar "admin boleh apa saja".

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use App\Models\User;
use App\States\Submission\Approved;
use Spatie\Activitylog\Models\Activity;

it('[SM-STATE-05] override Operator menyentuh pengajuan yang sudah terminal', function () {
    $owner = User::factory()->create();
    $detail = FormPhaseDetail::factory()->create();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => Approved::class,
    ]);

    // Tanpa override, pengajuan APPROVED terkunci untuk pemiliknya.
    expect($owner->can('update', $submission))->toBeFalse();

    $override = FormSubmissionOverride::create([
        'form_submission_id' => $submission->id,
        'form_phase_detail_id' => $detail->id,
        // granted_by NOT NULL — override tanpa penerbit tidak menjawab
        // "siapa yang memberi pengecualian ini", dan itu justru intinya.
        'granted_by' => User::factory()->create()->id,
        'reason' => 'Koreksi angka anggaran atas permintaan LPPM.',
        'expires_at' => now()->addWeek(),
        'is_active' => true,
    ]);

    expect($override->isValid())->toBeTrue()
        // BR-FE-09 — alasannya wajib terisi; override tanpa alasan tidak
        // meninggalkan jejak kenapa pengecualian itu diberikan.
        ->and($override->reason)->not->toBeEmpty()
        // BR-FE-10 — ber-scope satu submission + satu detail, bukan blanket.
        ->and($override->form_submission_id)->toBe($submission->id)
        ->and($override->form_phase_detail_id)->toBe($detail->id);
});

it('[SM-STATE-05] override yang sudah lewat tidak lagi membuka apa pun', function () {
    $detail = FormPhaseDetail::factory()->create();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => Approved::class,
    ]);

    $override = FormSubmissionOverride::create([
        'form_submission_id' => $submission->id,
        'form_phase_detail_id' => $detail->id,
        // granted_by NOT NULL — override tanpa penerbit tidak menjawab
        // "siapa yang memberi pengecualian ini", dan itu justru intinya.
        'granted_by' => User::factory()->create()->id,
        'reason' => 'Perpanjangan singkat.',
        'expires_at' => now()->subDay(),
        'is_active' => true,
    ]);

    // Kedaluwarsa sendiri — itu yang membedakannya dari permission permanen.
    expect($override->isValid())->toBeFalse();
});

it('[SM-STATE-05] penerbitan override tercatat di jejak audit', function () {
    $detail = FormPhaseDetail::factory()->create();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => Approved::class,
    ]);

    $override = FormSubmissionOverride::create([
        'form_submission_id' => $submission->id,
        'form_phase_detail_id' => $detail->id,
        // granted_by NOT NULL — override tanpa penerbit tidak menjawab
        // "siapa yang memberi pengecualian ini", dan itu justru intinya.
        'granted_by' => User::factory()->create()->id,
        'reason' => 'Koreksi atas permintaan LPPM.',
        'expires_at' => now()->addWeek(),
        'is_active' => true,
    ]);

    $entry = Activity::query()
        ->where('subject_type', FormSubmissionOverride::class)
        ->where('subject_id', $override->id)
        ->first();

    // Pengecualian terhadap aturan harus meninggalkan jejak siapa dan kenapa.
    expect($entry)->not->toBeNull()
        ->and($entry->properties['attributes']['reason'])->toContain('LPPM');
});
