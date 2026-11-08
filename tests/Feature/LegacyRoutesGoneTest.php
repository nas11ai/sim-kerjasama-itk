<?php

// tests/Feature/LegacyRoutesGoneTest.php
//
// fe-v2-design F99 — nama route yang hanya hidup untuk halaman legacy (W22, W46, W61),
// penugasan form evaluasi terpisah tanpa frame/skenario (W158), dan route ulir/komentar
// kembar (W159) sudah tidak terdaftar.

use Illuminate\Support\Facades\Route;

it('nama route legacy sudah tidak terdaftar', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'user.dashboard',
    'reviewer.dashboard',
    'admin.dashboard',
    'user.biodata.index',
    'reviewer.submissions.index',
    'reviewer.evaluation-form.show',
    'reviewer.evaluation-form.submitted',
    'reviewer.evaluation-form.progress',
    'reviewer.evaluation-form.download-summary',
    'admin.submissions.assign-evaluation-forms',
    'admin.submissions.assign-forms-bulk',
    'admin.form-assignments.remove',
    'admin.review-threads.store',
    'review-threads.store',
    'admin.review-comments.store',
    'reviewer.review-comments.store',
]);
