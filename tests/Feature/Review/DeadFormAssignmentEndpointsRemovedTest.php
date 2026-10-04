<?php

// tests/Feature/Review/DeadFormAssignmentEndpointsRemovedTest.php
//
// Tiga endpoint form evaluasi tidak punya pemanggil di frontend maupun skenario BDD:
// ubah penugasan, daftar penugasan per pengajuan, dan penguncian massal yang lewat
// tenggat. Penguncian tetap hidup sebagai Action LockEvaluationForm.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan endpoint form evaluasi yang tak dipanggil', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.form-assignments.update',
    'admin.submissions.form-assignments',
    'admin.maintenance.auto-lock-overdue',
]);
