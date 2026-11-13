<?php

// tests/Unit/Data/NotificationPayloadTest.php

use App\Data\Contract\NotificationPayload;

it('menyusun tiga kunci wajib', function () {
    $payload = new NotificationPayload(
        type: 'reviewer_assigned',
        title: 'Penugasan penilaian baru',
        message: 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
    );

    expect($payload->toArray())->toBe([
        'type' => 'reviewer_assigned',
        'title' => 'Penugasan penilaian baru',
        'message' => 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
    ]);
});

it('menggabungkan konteks tambahan', function () {
    $payload = new NotificationPayload(
        type: 'monev_stage_opened',
        title: 'Siklus monev dibuka',
        message: 'Batas pengisian 30 hari.',
        context: ['form_submission_id' => 7, 'form_phase_detail_id' => 3],
    );

    expect($payload->toArray())
        ->toHaveKey('form_submission_id', 7)
        ->and($payload->toArray())->toHaveKey('type', 'monev_stage_opened');
});

it('tidak membiarkan konteks menimpa kunci wajib', function () {
    // Konteks yang bisa menimpa `type` membuat layar notifikasi salah merender.
    $payload = new NotificationPayload(
        type: 'export_completed',
        title: 'Ekspor selesai',
        message: 'Siap diunduh.',
        context: ['type' => 'palsu'],
    );

    expect($payload->toArray()['type'])->toBe('export_completed');
});
