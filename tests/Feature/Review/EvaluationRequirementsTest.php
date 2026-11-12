<?php

// tests/Feature/Review/EvaluationRequirementsTest.php

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Queries\Review\EvaluationRequirements;
use Illuminate\Support\Facades\DB;

it('menyatakan tidak ada syarat saat pengajuan tanpa form phase detail', function () {
    $result = EvaluationRequirements::run(FormSubmission::factory()->create());

    expect($result['required'])->toBeFalse()
        ->and($result['has_forms'])->toBeFalse()
        ->and($result)->not->toHaveKey('total_forms');
});

it('menyatakan tidak ada syarat saat fase-nya tanpa form evaluasi', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    expect(EvaluationRequirements::run($submission)['has_forms'])->toBeFalse();
});

it('menghitung form seluruhnya dan yang wajib saja', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    // dua aktif, satu di antaranya wajib
    evaluationFormOn($detail, [], title: 'Substansi');
    evaluationFormOn($detail, [], required: false, title: 'Anggaran');

    $result = EvaluationRequirements::run($submission->fresh());

    expect($result['required'])->toBeTrue()
        ->and($result['total_forms'])->toBe(2)
        ->and($result['required_forms'])->toBe(1);
});

it('menjawab dengan satu kueri, bukan tiga', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);
    evaluationFormOn($detail, [], title: 'Substansi');

    // Relasi fase dihangatkan lebih dulu supaya yang terhitung hanya
    // pemindaian tabel form evaluasi — itu yang dulu dijalankan tiga kali.
    $submission = $submission->fresh();
    $submission->formPhaseDetail;

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        EvaluationRequirements::run($submission);
        expect(DB::getQueryLog())->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
    }
});
